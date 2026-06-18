FROM eclipse-temurin:17-jre-alpine
 
WORKDIR /app
 
COPY server.jar .
COPY config.yml .
 
RUN addgroup -S cnet && adduser -S cnet -G cnet \
    && chown -R cnet:cnet /app
 
USER cnet
 
EXPOSE 7860
 
ENTRYPOINT ["java", "-jar", "server.jar"]
