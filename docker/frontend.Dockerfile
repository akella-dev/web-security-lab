FROM node:24-alpine AS dependencies
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund

FROM node:24-alpine AS build
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1
COPY --from=dependencies /app/node_modules ./node_modules
COPY frontend/ ./
RUN npm run build

FROM node:24-alpine AS runtime
RUN addgroup -g 10001 -S academy && adduser -u 10001 -S academy -G academy
WORKDIR /app
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 PORT=3000 HOSTNAME=0.0.0.0
COPY --from=build --chown=academy:academy /app/public ./public
COPY --from=build --chown=academy:academy /app/.next/standalone ./
COPY --from=build --chown=academy:academy /app/.next/static ./.next/static
USER academy
EXPOSE 3000
HEALTHCHECK --interval=10s --timeout=3s --start-period=15s --retries=5 \
  CMD wget -q -O /dev/null http://127.0.0.1:3000/ || exit 1
CMD ["node", "server.js"]
