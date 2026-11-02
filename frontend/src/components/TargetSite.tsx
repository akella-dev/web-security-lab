"use client";

import { useState, type FormEvent } from "react";
import { Flower2, UserRound, Package, MessageCircle, Globe, KeyRound, Mail, ShieldCheck, ArrowRight } from "lucide-react";
import { surfaces, type Surface } from "@/lib/campaign";
import type { HistoryItem, StarterRequest, WorkbenchResponse } from "@/lib/lab";
import { ResponsePreview } from "./ResponsePreview";
import { HoneyStorefront } from "./HoneyStorefront";

type Field = { name: string; label: string; value: string; type?: string; min?: number };
type Action = { title: string; method: string; path: string; fields?: Field[]; body?: Record<string, unknown>; query?: string };
const actions: Record<Surface, Action[]> = {
  store: [
    { title: "Оформить набор за 4 990 ₽", method: "POST", path: "/api/store/orders", body: { product_id: "honey-set", quantity: 1, unit_price: 4990 } },
    { title: "Добавить липовый мёд · 890 ₽", method: "POST", path: "/api/cart/items", body: { sku: "honey-500" }, fields: [{ name: "quantity", label: "Количество банок", value: "1", type: "number", min: 1 }] },
    { title: "Найти в каталоге", method: "GET", path: "/api/catalog", query: "filter", fields: [{ name: "filter", label: "Название товара", value: "honey" }] },
    { title: "Применить купон", method: "POST", path: "/api/cart/coupon", fields: [{ name: "code", label: "Промокод · один раз на корзину", value: "HONEY20" }] },
    { title: "Создать новую корзину", method: "POST", path: "/api/cart/new" },
  ],
  profile: [
    { title: "Сохранить профиль", method: "PATCH", path: "/api/store/profile", fields: [{ name: "display_name", label: "Как к вам обращаться", value: "Студент" }] },
    { title: "Проверить доставку", method: "GET", path: "/api/store/shipments?format=json" },
  ],
  support: [{ title: "Открыть обращение №42", method: "GET", path: "/api/support/tickets/42" }],
  community: [
    { title: "Искать на сайте", method: "GET", path: "/api/search", query: "q", fields: [{ name: "q", label: "Что ищем?", value: "мёд" }] },
    { title: "Опубликовать отзыв", method: "POST", path: "/api/comments", fields: [{ name: "content", label: "Поделитесь впечатлениями", value: "Отличный мёд!", type: "textarea" }] },
    { title: "Открыть ленту как следующий посетитель", method: "GET", path: "/api/comments" },
  ],
  access: [
    { title: "Войти", method: "POST", path: "/api/auth/login", fields: [{ name: "email", label: "Email", value: "student@example.test" }, { name: "password", label: "Пароль", value: "", type: "password" }] },
    { title: "Получить письмо", method: "POST", path: "/api/reset/request", fields: [{ name: "email", label: "Восстановление · email", value: "student@example.test" }] },
    { title: "Подтвердить восстановление", method: "POST", path: "/api/reset/verify", fields: [{ name: "email", label: "Email", value: "student@example.test" }, { name: "token", label: "Токен из письма", value: "" }] },
    { title: "Войти с двухэтапной проверкой", method: "POST", path: "/api/auth/password", fields: [{ name: "email", label: "Email", value: "student@example.test" }, { name: "password", label: "Пароль", value: "honey123", type: "password" }] },
  ],
  services: [
    { title: "Показать предпросмотр", method: "POST", path: "/api/tools/preview", fields: [{ name: "url", label: "Адрес страницы", value: "https://docs.example.test/help" }] },
    { title: "Правила индексации · robots.txt", method: "GET", path: "/robots.txt" },
    { title: "Получить пропуск интеграции", method: "GET", path: "/api/auth/lab-token" },
  ],
};
const titles: Record<Surface, [string, string]> = {
  store: ["Немного лета. В каждой банке.", "Три сорта мёда, деревянная ложка и открытка. Подарочный набор «Медовое знакомство»."],
  profile: ["Личный кабинет", "Ваш профиль, покупки и доставка. student@example.test"],
  support: ["Служба заботы", "Алиса Воронова · alice@example.test. «Не пришёл счёт. Проверьте, пожалуйста, документ к моему заказу»."],
  community: ["Разговоры за чаем", "Найдите любимый сорт и поделитесь впечатлениями."],
  access: ["Рады видеть вас снова", "Учебный аккаунт: student@example.test · пароль honey123"],
  services: ["Сервисы магазина", "Интеграции, справочные страницы и инструменты публикации."],
};
export function TargetSite({ surface, onNavigate, onAction, busy, history, response, cartId }: {
  surface: Surface; onNavigate: (surface: Surface) => void; onAction: (request: StarterRequest) => Promise<void>; busy: boolean;
  history: HistoryItem[]; response: WorkbenchResponse | null; cartId: string;
}) {
  const request = (method: string, path: string, body: Record<string, unknown> = {}) => onAction({ method, path, body, headers: {}, mode: "single" });
  const last = (path: string) => [...history].reverse().find((item) => item.path === path)?.response.body;
  const order = last("/api/store/orders");
  const profile = last("/api/store/profile");
  const [authMode, setAuthMode] = useState("login");
  const [profileTab, setProfileTab] = useState("profile");
  const [serviceTab, setServiceTab] = useState("preview");
  const [gift, setGift] = useState(false);
  const renderAction = (action: Action) => <ActionForm key={action.title} action={action} busy={busy} onSubmit={(values) => { const body = { ...action.body, ...values }; return request(action.method, action.query ? `${action.path}?${action.query}=${encodeURIComponent(String(values[action.query]))}` : action.path, action.query ? {} : body); }} />;
  const contextualTitle = { store: "Подробности операции", profile: "Данные кабинета", support: "Обращение и документы", community: "На странице сообщества", access: "Ответ службы входа", services: "Результат проверки" }[surface];
  return <section className="target-site honey-shop" aria-label="Исследуемый магазин">
    <div className="target-address"><span className="window-dots">● ● ●</span><span>{surfaces.find((item) => item.id === surface)?.address}</span><small>Учебная копия · ваш экземпляр</small></div>
    <div className="honey-announcement">Собрано с заботой. Отправлено с теплом.<span>Семейная пасека с 1998 года</span></div>
    <header className="target-brand"><div><Flower2 size={32} /><strong>медовая лавка<small>с пасеки — к вашему столу</small></strong></div><button className="honey-account-link" onClick={() => onNavigate("profile")}><UserRound size={18} /> Личный кабинет</button></header>
    <nav className="target-nav" aria-label="Разделы магазина">{surfaces.map((item) => <button key={item.id} aria-current={surface === item.id ? "page" : undefined} onClick={() => onNavigate(item.id)}>{item.label}</button>)}</nav>
    <div className="target-content">
      {surface === "store" ? <HoneyStorefront request={request} history={history} busy={busy} cartId={cartId} gift={gift} setGift={setGift} /> : <div className="honey-page-heading"><span className="honey-eyebrow">МЕДОВАЯ ЛАВКА / {surfaces.find((item) => item.id === surface)?.label}</span><h2>{titles[surface][0]}</h2><p>{titles[surface][1]}</p></div>}
      {surface === "profile" && <div className="honey-account-layout"><aside className="honey-account-card"><span className="honey-avatar"><UserRound size={26} /></span><h3>{profile && typeof profile === "object" ? String(profile.display_name) : "Добро пожаловать"}</h3><p>student@example.test</p><span className="honey-role">{profile && typeof profile === "object" ? String(profile.role) : "student"}</span><div className="honey-local-tabs"><button aria-pressed={profileTab === "profile"} onClick={() => setProfileTab("profile")}><UserRound size={16} /> Мой профиль</button><button aria-pressed={profileTab === "orders"} onClick={() => setProfileTab("orders")}><Package size={16} /> Заказы и доставка</button></div></aside><section className="honey-content-card">{profileTab === "profile" ? <><h3>Как к вам обращаться?</h3><p>Это имя будет отображаться в вашем профиле.</p>{renderAction(actions.profile[0])}</> : <><h3>Заказы и доставка</h3>{order && typeof order === "object" ? <div className="honey-order-summary"><Package size={24} /><span>Заказ {String(order.order_id)}<strong>Оплачено {String(order.charged)} ₽</strong></span><b>Собираем</b></div> : <p>После покупки здесь появится подтверждение заказа. Статус учебной доставки уже доступен.</p>}{renderAction(actions.profile[1])}</>}</section></div>}
      {surface === "support" && <div className="honey-support-layout"><article className="honey-content-card"><div className="honey-ticket-meta"><span>ОБРАЩЕНИЕ №42</span><b>Ожидает ответа</b></div><h3>Не пришёл счёт</h3><div className="honey-person"><span className="honey-avatar">АВ</span><div><strong>Алиса Воронова</strong><small>alice@example.test</small></div></div><blockquote>Здравствуйте! Проверьте, пожалуйста, документ к моему заказу. Письмо со счётом так и не пришло.</blockquote>{renderAction(actions.support[0])}</article><aside className="honey-service-note"><MessageCircle size={28} /><h3>Мы рядом</h3><p>Поможем с заказом, доставкой и документами.</p><span>support@example.test</span><small>Ежедневно · 09:00–18:00</small></aside></div>}
      {surface === "community" && <><section className="honey-community-search"><div><h3>Найдите свой ответ</h3><p>Товары, истории пасеки и советы к чаепитию.</p></div>{renderAction(actions.community[0])}</section><div className="honey-community-layout"><section className="honey-content-card"><span className="honey-eyebrow">ВПЕЧАТЛЕНИЯ ПОКУПАТЕЛЕЙ</span><h3>Мёд, о котором говорят</h3><article className="honey-review"><div className="honey-person"><span className="honey-avatar">М</span><div><strong>Марина</strong><small>Покупатель магазина</small></div><span className="honey-stars" aria-label="5 из 5">★★★★★</span></div><p>Нежный мёд, аккуратная упаковка. Особенно приятно было найти открытку!</p></article>{renderAction(actions.community[2])}</section><section className="honey-content-card"><h3>А как вам наш мёд?</h3><p>Оставьте отзыв для следующего покупателя.</p>{renderAction(actions.community[1])}</section></div></>}
      {surface === "access" && <div className="honey-auth-layout"><aside className="honey-auth-story"><Mail size={32} /><h3>Ваш маленький<br />медовый уголок.</h3><p>Заказы, любимые вкусы и забота о каждой доставке — в одном месте.</p><span><ShieldCheck size={16} /> Доступ к вашему кабинету</span></aside><section className="honey-content-card honey-auth-form"><div className="honey-switcher" role="group" aria-label="Способ входа">{[["login", "Вход"], ["reset", "Восстановление"], ["mfa", "Два этапа"]].map(([id, title]) => <button key={id} aria-pressed={authMode === id} onClick={() => setAuthMode(id)}>{title}</button>)}</div><h3>{authMode === "login" ? "С возвращением" : authMode === "mfa" ? "Дополнительная проверка" : "Восстановим доступ"}</h3>{renderAction(actions.access[authMode === "login" ? 0 : authMode === "mfa" ? 3 : 1])}{authMode === "reset" && <details className="honey-reset-confirm"><summary>Уже есть токен из письма?</summary>{renderAction(actions.access[2])}</details>}</section></div>}
      {surface === "services" && <div className="honey-services-layout"><div className="honey-service-menu" role="group" aria-label="Сервис магазина">{[["preview", "Предпросмотр ссылки", Globe], ["robots", "Индексация сайта", Flower2], ["token", "Пропуск интеграции", KeyRound]].map(([id, label, Icon]) => { const ServiceIcon = Icon as typeof Globe; return <button key={String(id)} aria-pressed={serviceTab === id} onClick={() => setServiceTab(String(id))}><ServiceIcon size={20} /><span>{String(label)}</span><ArrowRight size={15} /></button>; })}</div><section className="honey-content-card"><h3>{serviceTab === "preview" ? "Как выглядит ваша ссылка?" : serviceTab === "robots" ? "Информация для поисковых систем" : "Подключение к сервисам"}</h3><p>{serviceTab === "preview" ? "Вставьте адрес — магазин загрузит страницу и покажет её содержимое." : serviceTab === "robots" ? "Посмотрите, какие страницы разрешено индексировать." : "Получите пропуск текущего пользователя для подключения к сервисам."}</p>{renderAction(actions.services[serviceTab === "preview" ? 0 : serviceTab === "robots" ? 1 : 2])}</section></div>}
      {response && <section className="target-result honey-operation" aria-live="polite"><details open={surface !== "store"} key={`${surface}-${history.length}`}><summary>{contextualTitle}<span>{response.status >= 400 ? "Требует внимания" : "Получен ответ"}</span></summary>{response.preview === "html" ? <ResponsePreview response={response} /> : <ResultBody value={response.body} />}<details><summary>Исходный ответ сервера</summary><pre>{typeof response.body === "string" ? response.body : JSON.stringify(response.body, null, 2)}</pre></details></details></section>}
    </div><footer className="target-footer">Медовая лавка · support@example.test <span>Все покупки и данные учебные</span></footer>
  </section>;
}
function ActionForm({ action, busy, onSubmit }: { action: Action; busy: boolean; onSubmit: (values: Record<string, string | number>) => Promise<void> }) {
  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values: Record<string, string | number> = {};
    const data = new FormData(event.currentTarget);
    for (const field of action.fields ?? []) values[field.name] = field.type === "number" ? Number(data.get(field.name)) : String(data.get(field.name));
    void onSubmit(values);
  }
  return <form className="target-form" onSubmit={submit}>{action.fields?.map((field) => <label key={field.name}>{field.label}{field.type === "textarea" ? <textarea name={field.name} defaultValue={field.value} required /> : <input name={field.name} defaultValue={field.value} type={field.type ?? "text"} min={field.min} required />}</label>)}<button disabled={busy} type="submit">{action.title}</button></form>;
}
function ResultBody({ value }: { value: unknown }) {
  if (value === null || value === undefined) return null;
  if (typeof value === "string") return <p className="result-text">{value}</p>;
  if (typeof value !== "object") return <span>{String(value)}</span>;
  if (Array.isArray(value)) return <div className="result-list">{value.map((item, index) => <ResultBody key={index} value={item} />)}</div>;
  const labels: Record<string, string> = { error: "Ошибка", message: "Сообщение", charged: "Оплачено, ₽", order_id: "Заказ", role: "Роль", display_name: "Имя", customer: "Покупатель", invoice: "Счёт", total: "Сумма, ₽", cart_total: "Итого в корзине, ₽", quantity: "Количество", delivery_preview: "Письмо в учебном ящике", token: "Пропуск", report: "Отчёт", content: "Содержимое", discounts_applied: "Скидок применено", mfa_verified: "Второй фактор подтверждён", session: "Сессия", name: "Название", service_index: "Индекс сервиса", service: "Сервис", trace: "Диагностика", timeline: "Порядок событий" };
  return <dl className="result-fields">{Object.entries(value).filter(([key]) => !["_links", "workflow", "query", "hint"].includes(key)).map(([key, item]) => <div key={key}><dt>{labels[key] ?? key}</dt><dd><ResultBody value={item} /></dd></div>)}</dl>;
}
