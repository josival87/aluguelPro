FROM alpine:3.22 AS source

ARG WPPCONNECT_VERSION=v2.10.1

RUN apk add --no-cache ca-certificates git \
    && git clone --depth 1 --branch "${WPPCONNECT_VERSION}" \
        https://github.com/wppconnect-team/wppconnect-server.git /source

FROM node:22.22.2-alpine AS base

WORKDIR /usr/src/wpp-server
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true

RUN apk add --no-cache \
        fftw-dev \
        g++ \
        gcc \
        libc6-compat \
        make \
        pkgconfig \
        python3 \
        vips \
        vips-dev

COPY --from=source /source/.yarnrc.yml ./
COPY --from=source /source/package.json ./
COPY --from=source /source/yarn.lock ./

RUN corepack enable \
    && corepack prepare yarn@4.14.1 --activate \
    && yarn install --immutable

FROM base AS build

COPY --from=source /source/ ./
COPY docker/wppconnect/config.ts ./src/config.ts

RUN yarn build

FROM build AS runtime

RUN apk add --no-cache chromium fftw vips

EXPOSE 21465
ENTRYPOINT ["node", "dist/server.js"]
