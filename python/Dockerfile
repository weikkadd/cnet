FROM python:3.11-alpine

WORKDIR /app

COPY app.py ./

ENV FILE_PATH=/app \
    SHOW_LOG=false

EXPOSE 7860

CMD ["python3", "app.py"]
