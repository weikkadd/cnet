FROM php:8.3-cli-alpine

WORKDIR /app

COPY cnet.php .

RUN groupadd -r cnet && useradd -r -g cnet --no-log-init cnet \
    && chown -R cnet:cnet /app

USER cnet

EXPOSE 7860

CMD ["php", "cnet.php"]
