import { createApp } from './app.js';
import { closeDatabase, connectDatabase } from './database.js';
import { config } from './config.js';

await connectDatabase();
const server = createApp().listen(config.PORT, config.HOST, () => {
  console.log(`Private data service listening on ${config.HOST}:${config.PORT}`);
});

async function shutdown() {
  server.close(async () => {
    await closeDatabase();
    process.exit(0);
  });
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
