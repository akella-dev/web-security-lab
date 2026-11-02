"use client";

import { BookOpen, Check, ChevronRight, FlaskConical, Lightbulb, LogOut, Radio, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { api } from "@/lib/api";
import { cases, chapters, type Surface } from "@/lib/campaign";
import { parseEditorJson, requestDraftOf, type HistoryItem, type Mission, type RequestDraft, type StarterRequest, type Student, type WorkbenchResponse } from "@/lib/lab";
import { RequestWorkbench } from "./RequestWorkbench";
import { MissionStages } from "./MissionStages";
import { TargetSite } from "./TargetSite";

const emptyDraft: RequestDraft = { method: "GET", path: "/", headers: "{}", body: "{}", mode: "single" };

export function StudentLab({ student, onSignOut }: { student: Student; onSignOut: () => Promise<void> }) {
  const [missions, setMissions] = useState<Mission[]>([]);
  const [selected, setSelected] = useState("price-tampering");
  const [surface, setSurface] = useState<Surface>("store");
  const [chapter, setChapter] = useState(0);
  const [draft, setDraft] = useState<RequestDraft>(emptyDraft);
  const [original, setOriginal] = useState<RequestDraft>(emptyDraft);
  const [response, setResponse] = useState<WorkbenchResponse | null>(null);
  const [history, setHistory] = useState<HistoryItem[]>([]);
  const [hints, setHints] = useState(0);
  const [tools, setTools] = useState(false);
  const [sending, setSending] = useState(false);
  const inFlight = useRef(false);
  const toolsPanel = useRef<HTMLElement>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState<string[]>([]);
  const current = missions.find((item) => item.slug === selected);
  const scenario = cases[selected];
  const completed = missions.filter((item) => item.status === "completed").length;
  const cartResponse = [...history].reverse().find((item) => item.path === "/api/cart/new")?.response.body;
  const cartId = cartResponse && typeof cartResponse === "object" ? String(cartResponse.cart_id) : "demo";

  useEffect(() => {
    Promise.all([api<Mission[]>("/api/challenges"), api<HistoryItem[]>("/api/workbench/history")])
      .then(([items, requests]) => { setMissions(items); setHistory(requests); })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : "Не удалось открыть полигон"))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (tools) toolsPanel.current?.scrollIntoView({ block: "start" });
  }, [tools]);

  async function dispatch(request: StarterRequest, capture = true) {
    if (inFlight.current) return;
    inFlight.current = true;
    setSending(true); setError("");
    try {
      const next = requestDraftOf(request);
      setDraft(next);
      if (capture) setOriginal(next);
      const result = await api<WorkbenchResponse>("/api/workbench/send", { method: "POST", body: JSON.stringify(request) });
      setResponse(result);
      setNotice(result.earned_challenges.filter((slug) => missions.find((item) => item.slug === slug)?.status !== "completed"));
      const [items, requests] = await Promise.all([api<Mission[]>("/api/challenges"), api<HistoryItem[]>("/api/workbench/history")]);
      setMissions(items); setHistory(requests);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Не удалось выполнить действие"); }
    finally { inFlight.current = false; setSending(false); }
  }
  async function send() {
    try { await dispatch({ ...draft, headers: parseEditorJson(draft.headers, "Заголовки") as Record<string, string>, body: parseEditorJson(draft.body, "Тело") }, false); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Проверьте JSON"); }
  }
  function select(slug: string) { setSelected(slug); setSurface(cases[slug].surface); setHints(0); setNotice([]); setResponse(null); }
  function prepare(request: StarterRequest) {
    const next = requestDraftOf(request);
    setDraft(next); setOriginal(next); setResponse(null); setError(""); setTools(true);
    if (tools) toolsPanel.current?.scrollIntoView({ block: "start" });
  }
  function restore(item: HistoryItem) { const next = requestDraftOf(item); setDraft(next); setOriginal(next); setResponse(item.response); setTools(true); }
  if (loading) return <div className="gate-loading">Подключаем ваш экземпляр магазина…</div>;
  if (!current) return <div className="gate-loading" role="alert">{error || "Программа недоступна. Обновите страницу."}</div>;

  return <div className="investigation-app">
    <header className="investigation-header"><Link href="/" className="investigation-logo"><FlaskConical size={23} /> WEB SECURITY LAB</Link><span className="lab-session"><i /> Личная лаборатория</span><a href="/handbook" target="_blank" rel="noreferrer"><BookOpen size={16} /> Справочник</a><span className="investigator-name">{student.name}</span><button aria-label="Выйти" onClick={() => void onSignOut().catch(() => setError("Не удалось выйти. Повторите попытку."))}><LogOut size={18} /></button></header>
    <div className="investigation-layout">
      <aside className="case-sidebar"><div className="case-intro"><small>ВАША ОПЕРАЦИЯ</small><h1>Из покупателя<br />в исследователя.</h1><p>Магазин запустился слишком рано. Найдите, что команда упустила.</p><div className="campaign-progress"><span style={{ width: `${missions.length ? completed / missions.length * 100 : 0}%` }} /></div><small>{completed} / {missions.length} находок подтверждено</small></div>
        <nav aria-label="Главы расследования">{chapters.map((item, index) => <div key={item.title} className="chapter"><button className={chapter === index ? "chapter-button active" : "chapter-button"} onClick={() => { setChapter(index); select(item.slugs[0]); }}><span>{String(index + 1).padStart(2, "0")}</span><strong>{item.title}<small>{item.slugs.filter((slug) => missions.find((m) => m.slug === slug)?.status === "completed").length} / {item.slugs.length} находок</small></strong><ChevronRight size={15} /></button>{chapter === index && <div className="chapter-cases"><p>{item.subtitle}</p>{item.slugs.map((slug) => <button key={slug} className={selected === slug ? "active" : ""} onClick={() => select(slug)}><span>{missions.find((m) => m.slug === slug)?.status === "completed" ? <Check size={14} /> : "○"}</span>{cases[slug].title}{missions.find((m) => m.slug === slug)?.stage_progress && <small className="case-stage-count">{missions.find((m) => m.slug === slug)!.stage_progress!.completed}/{missions.find((m) => m.slug === slug)!.stage_progress!.total}</small>}</button>)}</div>}</div>)}</nav><p className="sidebar-footnote">Главы идут от основ к цепочкам атак. Можно исследовать любую — прогресс сохранится.</p>
      </aside>
      <main className="investigation-main">
        <section className="case-brief"><div className="case-eyebrow"><span>ДЕЛО {String(chapter + 1).padStart(2, "0")} / {current.category}</span><span>{current.difficulty} · {current.estimated_minutes} мин</span></div><h2>{scenario.title}</h2><p>{scenario.brief}</p><div className="case-objective"><span>ЦЕЛЬ</span>{scenario.objective}{current.status === "completed" && <b><Check size={15} /> Подтверждено</b>}</div><div className="case-assistance"><details><summary>С чего начать</summary><p>{scenario.first}</p><p>Выполните действие на сайте. Его запрос появится в журнале внизу. Выберите запись, чтобы исследовать и изменить её.</p></details><button onClick={() => setHints((value) => Math.min(value + 1, current.hints.length))} disabled={hints === current.hints.length}><Lightbulb size={15} /> Подсказка {hints}/{current.hints.length}</button></div>{hints > 0 && <ol className="case-hints">{current.hints.slice(0, hints).map((hint) => <li key={hint}>{hint}</li>)}</ol>}</section>
        {!!current.stages?.length && <MissionStages stages={current.stages} onPrepare={prepare} busy={sending} />}
        {notice.length > 0 && <div className="finding-banner" role="status"><Check size={22} /><div><strong>Уязвимость подтверждена</strong>{notice.map((slug) => <p key={slug}>{cases[slug]?.consequence}</p>)}</div><button aria-label="Скрыть уведомление" onClick={() => setNotice([])}><X size={17} /></button></div>}
        {error && <p role="alert" className="form-error">{error}</p>}
        <TargetSite surface={surface} onNavigate={(next) => { setSurface(next); setResponse(null); }} onAction={dispatch} busy={sending} history={history} response={response} cartId={cartId} />
        <section className="traffic-dock"><header><strong><Radio size={17} /> Журнал трафика <span>{history.length}</span></strong><button disabled={!history.length} onClick={() => setTools(!tools)}>{tools ? "Свернуть инструменты" : "Открыть перехватчик"}</button></header><p>Каждое действие магазина оставляет запрос. Выберите запись, измените одно предположение и сравните результат.</p><div className="traffic-entries">{history.length ? history.slice(-5).reverse().map((item) => <button key={item.id} onClick={() => restore(item)}><span>{item.method}</span><code>{item.path}</code><b className={item.response_status >= 400 ? "traffic-error" : ""}>{item.response_status}</b><span>Исследовать ↗</span></button>) : <div className="traffic-empty">Сделайте что-нибудь на сайте: оформите заказ, сохраните профиль или воспользуйтесь поиском.</div>}</div></section>
        {tools && <section className="investigation-tools" ref={toolsPanel}><div className="tools-heading"><h2>Перехватчик запросов</h2><button onClick={() => setTools(false)} aria-label="Закрыть инструменты"><X size={19} /></button></div><p>Это виртуальный трафик магазина. Изменения отправляются в ваш личный экземпляр. «Параллельно» моделирует чередование двух обработчиков.</p><RequestWorkbench draft={draft} setDraft={setDraft} onSend={send} onReset={() => setDraft(original)} response={response} history={history} onRestore={restore} sending={sending} error={error} /></section>}
        {current.status === "completed" && <section className="case-debrief"><small>РАЗБОР НАХОДКИ</small><h3>{scenario.question}</h3><p>{current.learning_goals.join(". ")}.</p><p>В отчёте приложите исходный и изменённый запросы, наблюдаемое последствие и объяснение исправления. Преподавателю доступны этапы и последние события. Полные запросы приложите к отчёту.</p><button onClick={() => { const sequence = chapters.flatMap((item) => item.slugs); const next = sequence[sequence.indexOf(selected) + 1]; if (next) { setChapter(chapters.findIndex((item) => item.slugs.includes(next))); select(next); window.scrollTo({ top: 0, behavior: "smooth" }); } }} disabled={selected === "jwt-tampering"}>Следующее расследование <ChevronRight size={16} /></button></section>}
      </main>
    </div>
  </div>;
}
