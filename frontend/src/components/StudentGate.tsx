"use client";

import { ArrowRight, BookOpen, Fingerprint, ShieldCheck } from "lucide-react";
import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";

import { api, ApiError } from "@/lib/api";
import type { Student } from "@/lib/lab";

import { BrandMark } from "./BrandMark";
import { StudentLab } from "./StudentLab";

export function StudentGate() {
  const [student, setStudent] = useState<Student | null>(null);
  const [checking, setChecking] = useState(true);
  const [name, setName] = useState("");
  const [group, setGroup] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api<Student>("/api/session")
      .then(setStudent)
      .catch((reason: unknown) => {
        if (!(reason instanceof ApiError) || reason.status !== 401) {
          setError(reason instanceof Error ? reason.message : "Не удалось проверить сессию");
        }
      })
      .finally(() => setChecking(false));
  }, []);

  async function enroll(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    try {
      setSubmitting(true);
      setError("");
      setStudent(await api<Student>("/api/session", {
        method: "POST",
        body: JSON.stringify({ name, group }),
      }));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Не удалось войти");
    } finally {
      setSubmitting(false);
    }
  }

  async function signOut() {
    await api("/api/session", { method: "DELETE" });
    setStudent(null);
  }

  if (checking) {
    return <div className="gate-loading">Проверяем учебную сессию…</div>;
  }

  if (student) {
    return <StudentLab student={student} onSignOut={signOut} />;
  }

  return (
    <main className="enrollment-page academy-surface">
      <header className="academy-header"><Link className="academy-brand" href="/"><BrandMark /><strong>Web Security Lab</strong></Link><span className="academy-section-label">Практикум по безопасности</span><Link className="academy-back" href="/handbook"><BookOpen size={16} /> База знаний</Link></header>
      <div className="enrollment-layout">
      <section className="gate-copy">
        <span className="academy-eyebrow">УЧЕБНОЕ РАССЛЕДОВАНИЕ / HONEY.TEST</span>
        <h1>Обычный магазин.<br /><em>Необычные находки.</em></h1>
        <p>За витриной медовой лавки скрываются ошибки безопасности. Изучайте сайт, перехватывайте запросы и выясняйте, где сервер доверяет лишнему.</p>
        <div className="enrollment-case"><div><Fingerprint size={24} /><span>ОБЪЕКТ ИССЛЕДОВАНИЯ<strong>Медовая лавка</strong></span><span className="enrollment-badge">Учебный стенд</span></div><ol><li><b>01</b><span>Исследуйте магазин<small>Покупки, профиль, отзывы и поддержка</small></span></li><li><b>02</b><span>Найдите несоответствие<small>Сравните действия сайта и ответы сервера</small></span></li><li><b>03</b><span>Докажите причину<small>От простой ошибки до цепочки уязвимостей</small></span></li></ol><footer>5 глав <span>·</span> 16 расследований <span>·</span> От простого к сложному</footer></div>
        <div className="gate-rule"><ShieldCheck size={22} /><span><strong>Граница работы</strong>Только этот стенд и только выданные учебные аккаунты.</span></div>
      </section>
      <form className="gate-form" onSubmit={enroll}>
        <span className="enrollment-form-icon"><Fingerprint size={28} /></span>
        <span className="academy-eyebrow">ВАШЕ РАБОЧЕЕ МЕСТО</span>
        <h2>Начать расследование</h2>
        <p>Введите данные, которые преподаватель увидит в общей таблице.</p>
        <label htmlFor="student-name">Имя и фамилия</label>
        <input id="student-name" value={name} onChange={(event) => setName(event.target.value)} placeholder="Иван Петров" autoComplete="name" required />
        <label htmlFor="student-group">Группа</label>
        <input id="student-group" value={group} onChange={(event) => setGroup(event.target.value)} placeholder="ИВТ-21" required />
        {error ? <p className="form-error" role="alert">{error}</p> : null}
        <button className="primary-action" disabled={submitting} type="submit">
          {submitting ? "Подключаем…" : "Начать работу"}<ArrowRight size={18} />
        </button>
        <small>Сессия сохранится в этом браузере на 8 часов.</small>
        <Link className="enrollment-teacher" href="/teacher">Я преподаватель <ArrowRight size={14} /></Link>
      </form>
      </div>
    </main>
  );
}
