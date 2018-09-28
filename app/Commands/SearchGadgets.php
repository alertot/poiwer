<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use JakubOnderka\PhpConsoleColor\ConsoleColor;
use JakubOnderka\PhpConsoleHighlighter\Highlighter;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Builder\SplitItemBuilder;
use PhpSchool\CliMenu\Action\GoBackAction;
use PhpSchool\CliMenu\Action\ExitAction;
use PhpSchool\CliMenu\MenuItem\AsciiArtItem;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;

use App\Poiwer\Search;
use App\Poiwer\Gadget;
use App\Poiwer\SourceFileParser;
use App\Poiwer\InternalClass;
use App\Poiwer\AttributeProxy;
use App\Poiwer\ChainWriter;

ini_set('xdebug.max_nesting_level', 1000);

class SearchGadgets extends Command
{
    protected $signature = 'gadgets:search
                            {directory : The directory to inspect recursively}
                            {--set= : The set to search}
                            {--method= : The method to search}';

    protected $description = 'Search gadgets of certain type in a directory.';
    protected $directory = null;
    protected $searchInstance = null;
    private $chain = [];
    private $ap;
    private $mainMenu;

    public function getGadgetRelativePath($gadget)
    {
        return str_replace($this->directory, '', $gadget->getFilePath());
    }

    public function getGadgetString($gadget)
    {
        return sprintf(
            '%s::%s %50s',
            $gadget->getClassName($namespace = true),
            $gadget->name,
            $this->getGadgetRelativePath($gadget)
        );
    }
    public function openOneGadget($menu, $gadgets)
    {
        $gadget = $gadgets[0];

        foreach ($menu->getItems() as $item) {
            if ($item->getText() === $this->getGadgetString($gadget)) {
                // Clean screen
                deleteLastSearch($menu);

                $callable = $item->getSelectAction();
                $callable($menu);
                return;
            }
        }
    }

    public function search($menu, $builder, $gadget, $searchType)
    {
        $answer = $menu->askText()
            ->setPromptText("Enter any $searchType method name:")
            ->ask();
        $key = $answer->fetch();

        // This part must return an array of gadgets
        if ($searchType === 'global') {
            $gadgets = iterator_to_array($this->searchInstance->searchMethod($key));
        } elseif ($searchType === 'class') {
            $g = $this->searchInstance->searchClassMethod($key, $gadget);
            if ($g) {
                $gadgets = [$g];
            }
        }

        if (empty($gadgets)) {
            $flash = $menu->flash("No gadgets found :(");
            $flash->getStyle()->setBg('red');
            $flash->getStyle()->setFg('white');
            $flash->display();
            return;
        }

        // Clean screen
        deleteLastSearch($menu);

        $builder->addLineBreak()
            ->addLineBreak('● ○ ')->addLineBreak()
            ->addStaticItem(cli_yellow_bg . "> Gadgets found for $searchType [$key]")
            ->addLineBreak();

        $this->createGadgetsMenu($builder, $gadgets);

        $nGadgets = count($gadgets);

        // Show successful flash
        if ($nGadgets === 1) {
            $flash = $menu->flash("1 gadget found, open it!");
            $flash->getStyle()->setBg('green');
            $flash->display();

            $this->openOneGadget($menu, $gadgets);
        } else {
            $flash = $menu->flash("$nGadgets gadgets found, check below!");
            $flash->getStyle()->setBg('green');
            $flash->display();
        }

        $menu->redraw(true);
    }

    public function getTitle($gadget)
    {
        # Get tree and highlight serializable
        $tree = $gadget->getClassTree($this->searchInstance);
        $treeString = implode(' -> ', $tree);
        $treeString = str_replace(
            'Serializable',
            cli_red_bg . 'Serializable' . cli_reset,
            $treeString
        );

        $variableDescription = '';
        $i = 0;
        foreach ($this->ap->getFullProperties($gadget) as $name => $dict) {
            $value = $dict['value'];

            if ($value) {
                $variableDescription .= "($i) $name = $value; ";
            } else {
                $variableDescription .= "($i) $name; ";
            }
            $i++;
        }

        $firstLine = sprintf(
            "File: %s | Class: %s | Line: %d",
            $this->getGadgetRelativePath($gadget),
            $gadget->getClassName($namespace = true),
            $gadget->getLineNumber()
        );

        $nGadgetsInChain = count($this->chain);
        if ($nGadgetsInChain) {
            $firstLine .= " " . cli_green_bg . "($nGadgetsInChain in chain)" . cli_reset;
        }

        $title = sprintf(
            "%s" . PHP_EOL . "%s" . PHP_EOL . cli_yellow . "Variables: %s" . cli_reset,
            $firstLine,
            $treeString,
            $variableDescription
        );

        return $title;
    }

    public function setAttributeValue($menu, $gadget)
    {
        $description = '';
        $attributeNames = array_keys($this->ap->getFullProperties($gadget));

        $name = $menu->askNumber()
            ->setPromptText("Enter attribute number:")
            ->ask();

        $value = $menu->askText()
            ->setPromptText('Enter new value')
            ->ask();

        $attributeName = $attributeNames[$name->fetch()];
        $this->ap->setProperty($gadget, $attributeName, $value->fetch());

        $menu->setTitle($this->getTitle($gadget));
        $menu->redraw(true);
    }

    public function createGadgetSubMenu($builder, $gadget)
    {
        $cmd = &$this;
        $text = PHP_EOL . $gadget->getContents() . PHP_EOL;

        $menu = $builder->setTitle($this->getTitle($gadget))
            ->disableDefaultItems()
            ->addLineBreak()
            ->addAsciiArt($text, AsciiArtItem::POSITION_LEFT)
            ->addLineBreak('')
            ->addLineBreak('-')
            ->addLineBreak('')
            ->addItem('Go back', new GoBackAction)
            ->addItem(
                'Add to chain',
                function (CliMenu $menu) use ($cmd, $gadget) {
                    array_push($cmd->chain, $gadget);
                    $menu->setTitle($cmd->getTitle($gadget));
                    $menu->redraw(true);
                }
            )
            ->addItem(
                'Write chain to file',
                function (CliMenu $menu) use ($cmd, $gadget) {
                    $filepath = $menu->askText()
                        ->setPromptText('Enter file path')
                        ->ask();

                    $cw = new ChainWriter($cmd->chain);
                    $cw->generateCode($cmd->chain, $cmd->ap);
                    $cw->write($filepath->fetch());

                    $flash = $menu->flash("Saved ok!");
                    $flash->display();
                }
            )
            ->addItem(
                'Set attribute value',
                function (CliMenu $menu) use ($builder, $gadget, $cmd) {
                    $cmd->setAttributeValue($menu, $gadget);
                }
            )
            ->addItem('See file in editor', function () use ($gadget) {
                openInEditor($gadget->getFilePath(), $gadget->getLineNumber());
            })
            ->addItem(
                'Search method in current class',
                function (CliMenu $menu) use ($builder, $gadget, $cmd) {
                    $cmd->search($menu, $builder, $gadget, 'class');
                }
            )
            ->addItem(
                'Search method anywhere',
                function (CliMenu $menu) use ($builder, $gadget, $cmd) {
                    $cmd->search($menu, $builder, $gadget, 'global');
                }
            )
            ->build();

        $menu->addCustomControlMapping('c', function ($m) {
            deleteLastSearch($m);
            $m->redraw();
        });
    }

    public function createGadgetsMenu(&$builder, $gadgets)
    {
        $cmd = &$this;
        foreach ($gadgets as $gadget) {
            // Register gadget and its properties in AP
            $this->ap->add($gadget);

            // Create submenu
            $builder->addSubMenu(
                $this->getGadgetString($gadget),
                function (CliMenuBuilder $b) use ($cmd, $gadget) {
                    return $cmd->createGadgetSubMenu($b, $gadget);
                }
            );
        }
    }

    public function isTestFile($filepath)
    {
        return preg_match('#/tests?/#i', $filepath);
    }

    public function validateArgs()
    {
        $directory = $this->argument('directory');
        $searchSet = $this->option('set');
        $searchMethod = $this->option('method');

        if (!is_dir($directory)) {
            $this->error('Wrong value for `directory` argument.');
            exit(1);
        }

        // Add final slash to directory if it doesn't have
        if (substr($directory, -1) !== '/') {
            $directory = "$directory/";
        }

        if (!$searchSet and !$searchMethod) {
            $this->error('You must provide one set or method to search.');
            exit(1);
        }

        return [$directory, $searchSet, $searchMethod];
    }

    public function handle(): void
    {
        list($directory, $searchSet, $searchMethod) = $this->validateArgs();
        $this->directory = $directory;

        # Get file lists for root and subdirectories
        $file_lists = [
            $directory => glob("$directory*.php")
        ];
        foreach (glob("$directory*", GLOB_ONLYDIR) as $dir) {
            $file_lists[$dir] = getFiles($dir);
        }

        # Fill classes and gadgets
        $classes = [];
        $gadgets = [];
        foreach ($file_lists as $dir => $file_list) {
            $this->task(
                "Getting gadgets from directory {$dir}",
                function () use ($file_list, &$classes, &$gadgets) {
                    foreach ($file_list as $file) {
                        // Avoid test files
                        if ($this->isTestFile($file)) {
                            continue;
                        }

                        $sfp = new SourceFileParser($file);
                        foreach ($sfp->getClassesAndGadgets() as $obj) {
                            if ($obj instanceof InternalClass) {
                                $classes[$obj->name][] = $obj;
                            } elseif ($obj instanceof Gadget) {
                                $gadgets[$obj->name][] = $obj;
                            }
                        }
                    }
                }
            );
        }

        if (!$gadgets) {
            $this->error("No methods in $this->directory :(");
            exit(1);
        }

        // Create search instance
        $this->searchInstance = new Search($classes, $gadgets);
        // Create attribute proxy
        $this->ap = new AttributeProxy($this->searchInstance);

        // Create main menu
        $menu = ($builder = new CliMenuBuilder)
            ->setWidth($builder->getTerminal()->getWidth() - 2 * 2)
            ->setTitle("Gadgets found in $this->directory")
            ->setBackgroundColour('default')
            ->addLineBreak('');

        // Add initial gadgets
        if ($searchMethod) {
            $gadgets = $this->searchInstance->searchMethod($searchMethod);
        } else {
            $gadgets = $this->searchInstance->searchSet($searchSet);
        }
        $this->createGadgetsMenu($menu, $gadgets);

        // Add part at the bottom
        $menu = $menu->addLineBreak('')
            ->addlineBreak('-')
            ->build()
            ->open();
    }
}
