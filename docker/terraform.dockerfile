FROM --platform=linux/{{DOCKER_ARCH}} alpine:edge

RUN apk add --no-cache git openssh-client ca-certificates terraform py-pip groff jq
RUN pip install awscli

ENTRYPOINT ["terraform"]