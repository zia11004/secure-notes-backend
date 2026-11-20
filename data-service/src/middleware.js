import { timingSafeEqual } from 'node:crypto';
import { config } from './config.js';
import { ServiceError } from './errors.js';

/** Only the PHP API may call this service on the local loopback interface. */
export function requireServiceToken(request, _response, next) {
  const supplied = Buffer.from(request.get('x-service-token') ?? '');
  const expected = Buffer.from(config.INTERNAL_SERVICE_TOKEN);
  if (supplied.length !== expected.length || !timingSafeEqual(supplied, expected)) {
    return next(new ServiceError('UNAUTHORIZED', 'Service authentication is required.', 401));
  }

  next();
}
