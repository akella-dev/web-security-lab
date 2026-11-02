"use client";

import { Check, Clipboard, Clock3, KeyRound, RefreshCw, RotateCcw, Server, ShieldAlert, Users } from "lucide-react";
import { useCallback, useEffect, useMemo, useState, useSyncExternalStore } from "react";

import Link from "next/link";

import { api } from "@/lib/api";
import { groupMissions, type Mission, type MissionStage } from "@/lib/lab";

import { BrandMark } from "./BrandMark";

type Challenge = Omit<Mission, "status">;
type StageSummary = { completed: number; total: number; stages: MissionStage[] };
type StudentRow = { challenge_stages?: Record<string, StageSummary>; id: string; name: string; group: string; completed_challenges: string[]; progress: number; last_seen_at: string };
type LabEvent = { id: number; type: string; summary: string; metadata: { student_id?: string; path?: string; status?: number }; created_at: string };
type Overview = { stats: { students: number; completed: number; available: number }; students: StudentRow[]; challenges: Challenge[]; events: LabEvent[] };

const subscribeToOrigin = () => () => undefined;
const browserOrigin = () => window.location.origin;
const serverOrigin = () => "";

export function TeacherDashboard() {
  const studentUrl = useSyncExternalStore(subscribeToOrigin, browserOrigin, serverOrigin);
  const [key, setKey] = useState("");
  const [overview, setOverview] = useState<Overview | null>(null);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    if (!key.trim()) { setError("Введите ключ преподавателя из .env (TEACHER_TOKEN)."); return; }
    try {
      setError("");
      const next = await api<Overview>("/api/teacher/overview", { headers: { "X-Teacher-Key": key } });
      setOverview(next);
      setSelectedId((current) => current ?? next.students[0]?.id ?? null);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Не удалось загрузить сводку"); }
  }, [key]);

  useEffect(() => {
    if (!overview) return;
    const timer = window.setInterval(() => void load(), 5000);
    return () => window.clearInterval(timer);
  }, [load, overview]);

  const selected = overview?.students.find((student) => student.id === selectedId) ?? null;
  const modules = useMemo(() => groupMissions(overview?.challenges ?? []), [overview?.challenges]);
  const selectedEvents = useMemo(() => overview?.events.filter((event) => event.metadata.student_id === selectedId) ?? [], [overview, selectedId]);
  const total = overview?.challenges.length ?? 16;

  async function reset() {
    if (!window.confirm("Удалить всех студентов, их прогресс и историю запросов?")) return;
    try {
      await api("/api/teacher/reset", { method: "POST", headers: { "X-Teacher-Key": key } });
      setSelectedId(null);
      await load();
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Не удалось сбросить занятие"); }
  }

  return <main className="teacher-app academy-surface">
    <header className="teacher-header"><Link href="/" className="academy-brand"><BrandMark /><strong>Web Security Lab</strong></Link><span className="teacher-caption">Панель преподавателя</span><div className="teacher-connection"><i className={overview && !error ? "connected" : ""} />{overview ? (error ? "Ошибка обновления" : "Подключено · обновление каждые 5 с") : "Ожидает подключения"}</div></header>
    <div className="teacher-page-title"><div><span className="academy-eyebrow">УПРАВЛЕНИЕ ЗАНЯТИЕМ</span><h1>Рабочая группа</h1><p>Прогресс, находки и последние действия студентов.</p></div><Link href="/handbook">Открыть базу знаний ↗</Link></div>
    <section className="teacher-controls">
      <div className="control-group"><Server size={19} /><label htmlFor="student-url">Адрес для студентов</label><input id="student-url" readOnly value={studentUrl} placeholder="IPv4 не определён" /><button type="button" disabled={!studentUrl} onClick={() => void navigator.clipboard?.writeText(studentUrl)}><Clipboard size={17} />Скопировать</button></div>
      <div className="control-group"><KeyRound size={19} /><label htmlFor="teacher-key">Ключ доступа</label><input id="teacher-key" type="password" autoComplete="off" placeholder="Ключ из .env" value={key} onChange={(event) => setKey(event.target.value)} /><button className="control-primary" type="button" onClick={() => void load()}>Подключиться</button></div>
      <button className="icon-control" type="button" onClick={() => void load()}><RefreshCw size={17} />Обновить</button>
      <button className="danger-control" type="button" onClick={() => void reset()}><RotateCcw size={17} />Сбросить занятие</button>
    </section>
    <p className="teacher-network-note">Адрес взят из текущей вкладки. Для студентов откройте эту панель по сетевому IP компьютера преподавателя: localhost доступен только на самом компьютере. Ключ преподавателя: TEACHER_TOKEN в .env.</p>
    {error ? <p className="form-error teacher-error">{error}</p> : null}
    <section className="teacher-stats"><div><Users /><strong>{overview?.stats.students ?? 0}</strong><span>студентов</span></div><div><Check /><strong>{overview?.stats.completed ?? 0} из {overview?.stats.available ?? 0}</strong><span>решений</span></div><div><ShieldAlert /><strong>{(overview?.students ?? []).filter((student) => student.progress < total).length}</strong><span>ещё работают</span></div></section>
    <section className="student-table-panel">
      <div className="table-title"><h2>Студенты ({overview?.students.length ?? 0})</h2><span><Clock3 size={16} /> В таблице — прогресс по модулям</span></div>
      <div className="teacher-table-wrap"><table className="teacher-table course-teacher-table"><thead><tr><th>Студент</th><th>Группа</th>{modules.map((module) => <th key={module.name}>{module.name}</th>)}<th>Всего</th><th>Активность</th></tr></thead><tbody>
        {overview?.students.length ? overview.students.map((student) => <tr key={student.id} className={selectedId === student.id ? "selected-row" : ""} onClick={() => setSelectedId(student.id)}><td><button className="teacher-student-select" aria-pressed={selectedId === student.id} onClick={() => setSelectedId(student.id)}>{student.name}</button></td><td>{student.group}</td>{modules.map((module) => { const done = module.missions.filter((task) => student.completed_challenges.includes(task.slug)).length; return <td key={module.name}><span className={done === module.missions.length ? "module-score complete" : "module-score"}>{done}/{module.missions.length}</span></td>; })}<td><div className="table-progress"><span>{student.progress} / {total}</span><i><b style={{ width: `${total ? student.progress / total * 100 : 0}%` }} /></i></div></td><td>{formatDate(student.last_seen_at)}</td></tr>) : <tr><td colSpan={modules.length + 4} className="empty-table">{overview ? "Пока никто не подключился. Передайте студентам адрес стенда." : "Введите ключ и подключитесь, чтобы увидеть участников занятия."}</td></tr>}
      </tbody></table></div>
    </section>
    {selected ? <section className="student-detail-panel"><div className="selected-student"><span>{initials(selected.name)}</span><div><h2>{selected.name}</h2><p>{selected.group}</p></div><strong>Прогресс: {selected.progress} из {total}</strong></div><div className="student-detail-grid course-detail-grid"><div><h3>Задания по темам</h3>{modules.map((module) => <section className="teacher-module-detail" key={module.name}><strong>{module.name}</strong>{module.missions.map((task) => <TeacherTask key={task.slug} task={task} done={selected.completed_challenges.includes(task.slug)} progress={selected.challenge_stages?.[task.slug]} />)}</section>)}</div><div><h3>Последние HTTP-действия</h3>{selectedEvents.length ? selectedEvents.slice(0, 14).map((event) => <p className="event-row" key={event.id}><time>{new Date(event.created_at).toLocaleTimeString("ru-RU", { hour: "2-digit", minute: "2-digit" })}</time><span>{event.summary}{event.metadata.path ? <small>{event.metadata.path} · {event.metadata.status}</small> : null}</span></p>) : <p className="empty-detail">Действий пока нет.</p>}</div></div></section> : null}
    <footer className="teacher-footer"><ShieldAlert size={17} /> Виртуальные уязвимости не выполняют внешние сетевые, файловые или системные операции.</footer>
  </main>;
}

function Status({ done }: { done: boolean }) { return done ? <span className="status-check" aria-label="выполнено"><Check size={15} /></span> : <span className="status-pending" aria-label="не выполнено">—</span>; }
function initials(name: string): string { return name.split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join(""); }
function formatDate(value: string): string { return new Date(value).toLocaleString("ru-RU", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }); }

function TeacherTask({ task, done, progress }: { task: Challenge; done: boolean; progress?: StageSummary }) {
  return <details className="teacher-task-stages"><summary><Status done={done} /><span>{task.number}. {task.title}<small>{progress ? `Этапы: ${progress.completed} / ${progress.total}` : done ? "Выполнено" : "Не начато"}</small></span></summary>{progress && <ol>{progress.stages.map((stage) => <li key={stage.id} data-status={stage.status}><span>{stage.status === "completed" ? "✓" : stage.status === "current" ? "→" : "○"}</span><div>{stage.title}<small>{stage.status === "completed" ? "Выполнен" : stage.status === "current" ? "Текущий этап" : "Закрыт"}{stage.evidence ? ` · HTTP ${stage.evidence.status}` : ""}</small></div></li>)}</ol>}</details>;
}
