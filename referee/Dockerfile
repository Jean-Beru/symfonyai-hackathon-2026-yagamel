FROM python:3.13-slim

COPY ./requirements.txt /referee/requirements.txt
RUN pip install --no-cache-dir -r /referee/requirements.txt

COPY ./send.py /referee/

RUN useradd --uid 1000 --create-home referee
WORKDIR /referee
USER referee

ENTRYPOINT ["python3", "send.py"]
