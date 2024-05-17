# From upstream redis
FROM redis:latest

LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker-redis" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker-redis"

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s \
  CMD redis-cli PING
