export type MissionStatus = "pending" | "completed";
export type RequestMode = "single" | "parallel";

export type Student = { id: string; name: string; group: string };

export type StarterRequest = {
  method: string;
  path: string;
  headers: Record<string, string> | unknown[];
  body: Record<string, unknown> | unknown[] | string;
  mode: RequestMode;
};

export type Mission = {
  stages?: MissionStage[];
  stage_progress?: { completed: number; total: number };
  number: number;
  slug: string;
  title: string;
  module: string;
  difficulty: "Лёгкое" | "Среднее" | "Сложное" | "Экспертное";
  estimated_minutes: number;
  category: string;
  summary: string;
  mission: string;
  safety_boundary: string;
  learning_goals: string[];
  hints: string[];
  starter_request: StarterRequest;
  status: MissionStatus;
};

export type MissionStage = {
  id: string;
  title: string;
  status: "current" | "completed" | "locked";
  instruction?: string;
  resources?: { label: string; value: string }[];
  request?: StarterRequest;
  evidence?: { request_id: number | null; method: string; path: string; status: number };
};

export type RequestDraft = { method: string; path: string; headers: string; body: string; mode: RequestMode };
export type WorkbenchResponse = {
  status: number;
  headers: Record<string, string>;
  body: Record<string, unknown> | string;
  earned_challenges: string[];
  preview: "json" | "text" | "html";
};
export type HistoryItem = StarterRequest & { id: number; response_status: number; response: WorkbenchResponse; created_at: string };

export function progressOf(items: ReadonlyArray<{ status: MissionStatus }>) {
  const completed = items.filter((item) => item.status === "completed").length;
  const total = items.length;
  return { completed, total, percent: total === 0 ? 0 : Math.round((completed / total) * 100) };
}

export function groupMissions<T extends { module: string }>(missions: readonly T[]): Array<{ name: string; missions: T[] }> {
  const groups: Array<{ name: string; missions: T[] }> = [];
  for (const mission of missions) {
    const current = groups.at(-1);
    if (!current || current.name !== mission.module) groups.push({ name: mission.module, missions: [mission] });
    else current.missions.push(mission);
  }
  return groups;
}

export function requestDraftOf(request: StarterRequest): RequestDraft {
  const headers = Array.isArray(request.headers) ? {} : request.headers;
  const body = Array.isArray(request.body) && request.body.length === 0 ? {} : request.body;

  return {
    method: request.method,
    path: request.path,
    headers: JSON.stringify(headers, null, 2),
    body: typeof body === "string" ? body : JSON.stringify(body, null, 2),
    mode: request.mode,
  };
}

export function parseEditorJson(value: string, label: string): Record<string, unknown> {
  if (value.trim() === "") return {};
  const parsed: unknown = JSON.parse(value);
  if (!parsed || Array.isArray(parsed) || typeof parsed !== "object") throw new Error(`${label} должно быть JSON-объектом.`);
  return parsed as Record<string, unknown>;
}

export function formatResponseText(response: WorkbenchResponse): string {
  const headers = Object.entries(response.headers)
    .map(([key, value]) => `${key}: ${value}`)
    .join("\n");
  const body = typeof response.body === "string" ? response.body : JSON.stringify(response.body, null, 2);

  return `${headers}\n\n${body}`;
}

export function buildReviewDocument(content: string): string {
  return `<!doctype html>
<html lang="ru"><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src 'none'; connect-src 'none'; form-action 'none'; base-uri 'none'">
<style>body{margin:0;padding:16px;color:#172033;font:14px/1.5 system-ui,sans-serif;overflow-wrap:anywhere}article{padding:12px;border:1px solid #ddd;border-radius:8px}</style>
<script>window.alert=function(value){parent.postMessage({type:'lab-xss-alert',value:String(value)},'*')}</script>
</head><body>${content}</body></html>`;
}
