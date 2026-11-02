"use client";

import { Clock3, Copy, Play, RotateCcw, Zap } from "lucide-react";

import type { HistoryItem, RequestDraft, WorkbenchResponse } from "@/lib/lab";

import { ResponsePreview } from "./ResponsePreview";

export function RequestWorkbench({ draft, setDraft, onSend, onReset, response, history, onRestore, sending, error }: {
  draft: RequestDraft;
  setDraft: (draft: RequestDraft) => void;
  onSend: () => Promise<void>;
  onReset: () => void;
  response: WorkbenchResponse | null;
  history: HistoryItem[];
  onRestore: (item: HistoryItem) => void;
  sending: boolean;
  error: string;
}) {
  return <div className="workbench-grid">
    <section className="request-panel">
      <div className="panel-kicker"><span>HTTP request</span><button type="button" onClick={onReset}><RotateCcw size={14} />Исходный</button></div>
      <div className="request-line">
        <select aria-label="HTTP-метод" value={draft.method} onChange={(event) => setDraft({ ...draft, method: event.target.value })}>
          {['GET', 'POST', 'PATCH', 'PUT', 'DELETE'].map((method) => <option key={method}>{method}</option>)}
        </select>
        <input aria-label="Виртуальный путь" value={draft.path} onChange={(event) => setDraft({ ...draft, path: event.target.value })} />
      </div>
      <label htmlFor="request-headers">Заголовки · JSON</label>
      <textarea id="request-headers" className="code-editor headers-editor" spellCheck={false} value={draft.headers} onChange={(event) => setDraft({ ...draft, headers: event.target.value })} />
      <label htmlFor="request-body">Тело · JSON</label>
      <textarea id="request-body" className="code-editor body-editor" spellCheck={false} value={draft.body} onChange={(event) => setDraft({ ...draft, body: event.target.value })} />
      <div className="dispatch-row">
        <div className="mode-switch" role="group" aria-label="Режим отправки">
          <button type="button" className={draft.mode === "single" ? "active" : ""} onClick={() => setDraft({ ...draft, mode: "single" })}><Play size={14} />Один запрос</button>
          <button type="button" className={draft.mode === "parallel" ? "active" : ""} onClick={() => setDraft({ ...draft, mode: "parallel" })}><Zap size={14} />Параллельно ×2</button>
        </div>
        <button className="send-request" type="button" disabled={sending} onClick={() => void onSend()}>{sending ? "Отправляем…" : "Отправить"}<Play size={16} /></button>
      </div>
      {error ? <p className="form-error">{error}</p> : null}
    </section>
    <ResponsePreview response={response} />
    <section className="history-panel">
      <div className="panel-kicker"><span><Clock3 size={14} /> История запросов</span><small>{history.length}</small></div>
      <div className="history-list">{history.length ? history.toReversed().slice(0, 12).map((item) => <button type="button" key={item.id} onClick={() => onRestore(item)}><span className={`method method-${item.method.toLowerCase()}`}>{item.method}</span><strong>{item.path}</strong><em className={item.response_status < 400 ? "history-ok" : "history-error"}>{item.response_status}</em><Copy size={14} /></button>) : <p>Запросов пока нет. История поможет сравнивать ответы и строить цепочки.</p>}</div>
    </section>
  </div>;
}
