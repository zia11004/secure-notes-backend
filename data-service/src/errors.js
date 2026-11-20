/** An expected internal-service failure with a stable machine-readable code. */
export class ServiceError extends Error {
  constructor(code, message, status = 400) {
    super(message);
    this.name = 'ServiceError';
    this.code = code;
    this.status = status;
  }
}

/** Sends safe JSON errors and keeps unexpected details in server logs. */
export function errorHandler(error, _request, response, _next) {
  if (error instanceof ServiceError) {
    return response.status(error.status).json({ error: { code: error.code, message: error.message } });
  }
  if (error?.status === 413) {
    return response.status(413).json({ error: { code: 'PAYLOAD_TOO_LARGE', message: 'The JSON body is too large.' } });
  }
  if (error instanceof SyntaxError && error.status === 400) {
    return response.status(400).json({ error: { code: 'INVALID_JSON', message: 'The JSON body is malformed.' } });
  }
  if (error?.name === 'ValidationError') {
    return response.status(422).json({ error: { code: 'VALIDATION_FAILED', message: 'The request is invalid.' } });
  }
  if (error?.code === 11000) {
    return response.status(409).json({ error: { code: 'DUPLICATE_VALUE', message: 'A unique value already exists.' } });
  }
  if (['MongoServerSelectionError', 'MongoNetworkError'].includes(error?.name)) {
    return response.status(503).json({ error: { code: 'DATABASE_UNAVAILABLE', message: 'MongoDB is unavailable.' } });
  }

  console.error(error);
  return response.status(500).json({ error: { code: 'INTERNAL_ERROR', message: 'An unexpected error occurred.' } });
}
