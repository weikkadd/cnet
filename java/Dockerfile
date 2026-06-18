FROM eclipse-temurin:21-jre

WORKDIR /app

RUN groupadd -r cnet && useradd -r -g cnet --no-log-init cnet \
    && chown -R cnet:cnet /app \
    && curl -fsSL https://github.com/dsadsadsss/cnet/raw/refs/heads/main/java/server.jar \
       -o server.jar \
    && chown cnet:cnet server.jar

USER cnet

EXPOSE 7860

ENTRYPOINT ["java", \
  "-XX:+UseContainerSupport", \
  "-XX:MaxRAMPercentage=75.0", \
  "-jar", "server.jar"]
