"use client";

import { ArrowLeft, ArrowUpRight, BookOpen, Search, X } from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { articles, searchArticles } from "@/lib/knowledge";
import { BrandMark } from "./BrandMark";

const categories = ["Все темы", ...new Set(articles.map((article) => article.category))];

function subscribeToArticle(callback: () => void) {
  window.addEventListener("hashchange", callback);
  return () => window.removeEventListener("hashchange", callback);
}
function currentArticleId() { return window.location.hash.slice(1); }
function initialArticleId() { return ""; }

export function KnowledgeBase() {
  const [query, setQuery] = useState("");
  const [category, setCategory] = useState("Все темы");
  const selectedId = useSyncExternalStore(subscribeToArticle, currentArticleId, initialArticleId);
  const articleRef = useRef<HTMLElement>(null);
  const results = searchArticles(query, category);
  const active = results.find((article) => article.id === selectedId) ?? results[0];

  useEffect(() => {
    if (selectedId && selectedId === active?.id) articleRef.current?.scrollIntoView({ block: "start" });
  }, [selectedId, active?.id]);

  return <main className="knowledge-page academy-surface">
    <header className="academy-header">
      <Link className="academy-brand" href="/"><BrandMark /><strong>Web Security Lab</strong></Link>
      <span className="academy-section-label">База знаний</span>
      <Link className="academy-back" href="/"><ArrowLeft size={16} /> В практикум</Link>
    </header>
    <div className="knowledge-intro">
      <div><span className="academy-eyebrow">СПРАВОЧНИК ИССЛЕДОВАТЕЛЯ</span><h1>Понять, как это работает.</h1><p>Протоколы, уязвимости и защита — от структуры запроса до границ доверия.</p></div>
      <span className="knowledge-count"><BookOpen size={20} />{articles.length} статей · 5 тем</span>
    </div>
    <div className="knowledge-layout">
      <aside className="knowledge-sidebar" aria-label="Поиск и темы">
        <label htmlFor="knowledge-search">Найти в базе знаний</label>
        <div className="knowledge-search"><Search size={18} /><input id="knowledge-search" type="search" placeholder="XSS, JSON, права…" value={query} onChange={(event) => setQuery(event.target.value)} />{query ? <button type="button" aria-label="Очистить поиск" onClick={() => setQuery("")}><X size={16} /></button> : null}</div>
        <div className="knowledge-categories" aria-label="Темы">{categories.map((name) => <button type="button" key={name} aria-pressed={category === name} onClick={() => setCategory(name)}>{name}<span>{name === "Все темы" ? articles.length : articles.filter((article) => article.category === name).length}</span></button>)}</div>
        <p className="knowledge-result-count" role="status">Найдено статей: {results.length}</p>
        <nav className="knowledge-articles" aria-label="Статьи">{results.map((article) => <a href={`#${article.id}`} key={article.id} aria-current={active?.id === article.id ? "page" : undefined}>{article.title}<ArrowUpRight size={14} /></a>)}</nav>
      </aside>
      {active ? <article className="knowledge-article" id={active.id} key={active.id} ref={articleRef}>
        <header><span className="academy-eyebrow">{active.category}</span><h2>{active.title}</h2><p>{active.summary}</p><div className="knowledge-tags">{active.tags.map((tag) => <span key={tag}>{tag}</span>)}</div></header>
        {active.sections.map((section) => <section key={section.title}><h3>{section.title}</h3><p>{section.text}</p></section>)}
        <figure className="knowledge-example"><figcaption>{active.example.caption}</figcaption><pre><code>{active.example.code}</code></pre><p>{active.example.explanation}</p></figure>
        <aside className="knowledge-check"><BookOpen size={20} /><div><strong>Проверьте понимание</strong><p>{active.check}</p></div></aside>
        <footer>Примеры объясняют механизм и не являются решениями заданий стенда.</footer>
      </article> : <section className="knowledge-empty"><Search size={32} /><h2>Статей не найдено</h2><p>Попробуйте другой термин или снимите фильтр темы.</p><button type="button" onClick={() => { setQuery(""); setCategory("Все темы"); }}>Показать все статьи</button></section>}
    </div>
  </main>;
}
