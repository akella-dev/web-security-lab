"use client";

import { Check, LockKeyhole, ArrowDownToLine } from "lucide-react";
import type { MissionStage, StarterRequest } from "@/lib/lab";

export function MissionStages({ stages, onPrepare, busy }: {
  stages: MissionStage[]; onPrepare: (request: StarterRequest) => void; busy: boolean;
}) {
  const done = stages.filter((stage) => stage.status === "completed").length;
  return <section className="mission-stages" aria-label="Этапы расследования">
    <header><strong>План расследования</strong><span role="status">Завершено этапов: {done} из {stages.length}</span></header>
    <ol>{stages.map((stage, index) => <li key={`${stage.id}-${stage.status}`} className={`mission-stage ${stage.status}`}>
      <span className="stage-marker" aria-hidden="true">{stage.status === "completed" ? <Check size={15} /> : stage.status === "locked" ? <LockKeyhole size={14} /> : index + 1}</span>
      {stage.status === "locked" ? <div className="stage-locked"><strong>{stage.title}</strong><small>После предыдущего этапа</small></div> : <details open={stage.status === "current"}>
        <summary><strong>{stage.title}</strong><span>{stage.status === "completed" ? "Выполнен" : "Текущий этап"}</span></summary>
        <p>{stage.instruction}</p>
        {!!stage.resources?.length && <dl>{stage.resources.map((resource) => <div key={resource.label}><dt>{resource.label}</dt><dd>{resource.value}</dd></div>)}</dl>}
        {stage.request && <div className="stage-request"><code>{stage.request.method} {stage.request.path}</code><button type="button" disabled={busy} onClick={() => onPrepare(stage.request!)}><ArrowDownToLine size={15} /> Подготовить запрос</button><small>Заполнит перехватчик. Отправьте запрос самостоятельно.</small></div>}
        {stage.evidence && <div className="stage-evidence">Подтверждено запросом{stage.evidence.request_id !== null ? ` №${stage.evidence.request_id}` : ""}: <code>{stage.evidence.method} {stage.evidence.path}</code><span>HTTP {stage.evidence.status}</span></div>}
      </details>}
    </li>)}</ol>
  </section>;
}
