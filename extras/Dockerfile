FROM python:3.6.6-alpine

RUN apk add --update build-base git libxml2-dev libxslt-dev

RUN git clone https://github.com/alertot/detectem.git /detectem
RUN pip install -e /detectem
RUN pip install bottle

EXPOSE 5723

USER nobody
WORKDIR /detectem
ENTRYPOINT python detectem/ws.py
