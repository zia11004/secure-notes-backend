/** Keeps MongoDB identifiers and dates stable at the private HTTP boundary. */
export function formatDocument(document) {
  if (!document) return null;
  const value = document.toObject?.() ?? document;
  const { _id, passwordHash, ...fields } = value;
  const formatted = Object.fromEntries(Object.entries(fields).map(([key, item]) => {
    if (item instanceof Date) return [key, item.toISOString()];
    if (key.endsWith('Id') && item !== null && item !== undefined) return [key, String(item)];
    return [key, item];
  }));
  return {
    id: String(_id),
    ...formatted,
  };
}

/** Builds one consistent page envelope for every list endpoint. */
export function pageResult(items, total, page, perPage) {
  return { items, total, page, perPage, pages: Math.ceil(total / perPage) };
}
