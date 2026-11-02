export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message);
  }
}

export async function api<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(path, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...init?.headers,
    },
  });
  let payload: {
    data?: T;
    error?: { message?: string };
  };
  try {
    payload = await response.json();
  } catch {
    throw new ApiError("Сервер вернул некорректный ответ. Если стенд обновляется, дождитесь запуска и обновите страницу.", response.status);
  }
  if (!payload || typeof payload !== "object") {
    throw new ApiError("Не удалось прочитать ответ сервера. Обновите страницу или повторите действие.", response.status);
  }
  if (!response.ok || payload.data === undefined) {
    throw new ApiError(payload.error?.message ?? "Запрос не выполнен", response.status);
  }
  return payload.data;
}

export function rubles(kopecks: number): string {
  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 0,
  }).format(kopecks);
}
