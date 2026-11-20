import express from 'express';
import helmet from 'helmet';
import mongoose from 'mongoose';
import { errorHandler, ServiceError } from './errors.js';
import { requireServiceToken } from './middleware.js';
import { internalRoutes } from './routes/internal.js';

/** Creates the authenticated HTTP boundary for the Mongoose repositories. */
export function createApp() {
  const app = express();
  app.disable('x-powered-by');
  app.use(helmet());
  app.use(requireServiceToken);
  app.use(express.json({ limit: '64kb', strict: true }));

  app.get('/health', async (_request, response) => {
    if (mongoose.connection.readyState !== 1) {
      throw new ServiceError('DATABASE_UNAVAILABLE', 'MongoDB is unavailable.', 503);
    }
    await mongoose.connection.db.admin().ping();
    response.json({ status: 'ok' });
  });

  app.use(internalRoutes);

  app.use((_request, _response, next) => next(new ServiceError('NOT_FOUND', 'Resource not found.', 404)));
  app.use(errorHandler);
  return app;
}
