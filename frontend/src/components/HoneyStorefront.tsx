"use client";

import { useState } from "react";
import { ArrowRight, Check, Gift, Leaf, Minus, PackageCheck, Plus, Search, ShoppingBag, Trash2, Truck } from "lucide-react";
import type { HistoryItem } from "@/lib/lab";

export type ShopRequest = (method: string, path: string, body?: Record<string, unknown>) => Promise<void>;
const money = (value: number) => new Intl.NumberFormat("ru-RU").format(value) + " ₽";

export function HoneyStorefront({ request, history, busy, cartId, gift, setGift }: { request: ShopRequest; history: HistoryItem[]; busy: boolean; cartId: string; gift: boolean; setGift: (value: boolean) => void }) {
  const [quantity, setQuantity] = useState(1);
  const [filter, setFilter] = useState("");
  const [coupon, setCoupon] = useState("");
  const [couponOpen, setCouponOpen] = useState(false);
  const [category, setCategory] = useState("all");
  const latest = (path: string) => [...history].reverse().find((item) => item.path.split("?")[0] === path);
  const lastNewCart = latest("/api/cart/new");
  const cartRequest = latest("/api/cart/items");
  const cart = cartRequest && (!lastNewCart || cartRequest.id > lastNewCart.id) && typeof cartRequest.response.body === "object" ? cartRequest.response.body : null;
  const count = Number(cart?.quantity ?? 0);
  const honeyTotal = Number(cart?.cart_total ?? 0);
  const order = latest("/api/store/orders");
  const orderBody = typeof order?.response.body === "object" ? order.response.body : null;
  const catalog = latest("/api/catalog");
  const catalogBody = typeof catalog?.response.body === "object" ? catalog.response.body : null;
  const hidden = Array.isArray(catalogBody?.items) && catalogBody.items.some((item) => item && typeof item === "object" && "hidden" in item && item.hidden);
  const couponRequest = [...history].reverse().find((item) => item.path === "/api/cart/coupon" && typeof item.body === "object" && !Array.isArray(item.body) && (item.body.cart_id ?? "demo") === cartId);
  const couponResult = couponRequest?.response;
  const couponBody = couponResult && typeof couponResult.body === "object" ? couponResult.body : null;
  const total = honeyTotal + (gift ? 4990 : 0);
  const hasItems = gift || count !== 0;

  async function checkout() {
    await request("POST", "/api/store/orders", { product_id: gift ? "honey-set" : "honey-500", quantity: 1, unit_price: total });
  }

  return <>
    <section className="honey-feature"><div><span className="honey-eyebrow">СЕМЕЙНАЯ ПАСЕКА · УРОЖАЙ 2026</span><h2>Хороший день<br />начинается с мёда.</h2><p>Чистый состав. Знакомая пасека.<br />Маленькое удовольствие, которым хочется делиться.</p><button className="honey-primary" onClick={() => { setCategory("gifts"); document.getElementById("honey-catalog")?.scrollIntoView({ block: "nearest" }); }}>Выбрать подарок <ArrowRight size={16} /></button></div><div className="honey-feature-art" aria-label="Липовый и цветочный мёд"><HoneyJar variant="flower" /><HoneyJar variant="linden" /><span className="honey-seal">100%<small>натуральный мёд</small></span></div></section>
    <div className="honey-promises"><span><Leaf size={16} /> Только мёд. Ничего лишнего.</span><span><Gift size={16} /> Упакуем с заботой</span><span><Truck size={16} /> Доставка от 2 000 ₽</span></div>
    <div className="honey-shopping-layout">
      <section className="honey-catalog" id="honey-catalog"><div className="honey-section-heading"><div><span className="honey-eyebrow">С ПАСЕКИ НА ВАШ СТОЛ</span><h3>Выберите свой мёд</h3></div><small>Без добавок и спешки</small></div>
        <form className="honey-search" onSubmit={(event) => { event.preventDefault(); void request("GET", `/api/catalog?filter=${encodeURIComponent(filter)}`); }}><Search size={17} /><input aria-label="Поиск по каталогу" placeholder="Название или сорт мёда" value={filter} onChange={(event) => setFilter(event.target.value)} /><button disabled={busy} type="submit">Найти</button></form>
        <div className="honey-categories" role="group" aria-label="Категория товаров">{[["all", "Всё"], ["honey", "Мёд"], ["gifts", "Подарки"]].map(([id, label]) => <button key={id} aria-pressed={category === id} onClick={() => setCategory(id)}>{label}</button>)}</div>
        {catalog && <div className="honey-search-result" aria-live="polite">{catalog.response.status >= 400 ? String(catalogBody?.error ?? "Не удалось выполнить поиск") : <>Найдено позиций: {Array.isArray(catalogBody?.items) ? catalogBody.items.length : 0}{Array.isArray(catalogBody?.items) && <ul>{catalogBody.items.map((item, index) => <li key={index}>{String(item.name)}{item.hidden ? " · закрытая коллекция" : ""}</li>)}</ul>}</>}</div>}
        <div className="honey-product-grid">
          {category !== "honey" && <article className="honey-product"><div className="honey-product-visual gift-visual"><span className="honey-product-badge">Для особого случая</span><div className="honey-gift-box"><HoneyJar variant="flower" /><HoneyJar variant="linden" /><HoneyJar variant="buckwheat" /></div><span className="honey-product-weight">3 сорта · ложка · открытка</span></div><div className="honey-product-copy"><small>ПОДАРОЧНЫЙ НАБОР</small><h4>Медовое знакомство</h4><p>Три разных характера в одной коробке. Для тех, кому хочется пожелать тёплого дня.</p><div className="honey-product-bottom"><strong>4 990 ₽</strong><button disabled={busy || gift} onClick={() => setGift(true)}>{gift ? <><Check size={15} /> В корзине</> : <><Plus size={15} /> В корзину</>}</button></div></div></article>}
          {category !== "gifts" && <article className="honey-product"><div className="honey-product-visual linden-visual"><span className="honey-product-badge">Любимый сорт</span><HoneyJar variant="linden" /><span className="honey-product-weight">Стеклянная банка · 500 г</span></div><div className="honey-product-copy"><small>МОНОФЛОРНЫЙ МЁД</small><h4>Липовый</h4><p>Светлый, нежный, с цветочным ароматом. К утреннему тосту и вечернему чаю.</p><div className="honey-quantity"><span>Количество</span><div><button aria-label="Уменьшить количество" disabled={quantity <= 1 || busy} onClick={() => setQuantity((value) => Math.max(1, value - 1))}><Minus size={14} /></button><output aria-label="Количество банок">{quantity}</output><button aria-label="Увеличить количество" disabled={busy || quantity >= 99} onClick={() => setQuantity((value) => value + 1)}><Plus size={14} /></button></div></div><div className="honey-product-bottom"><strong>890 ₽ <small>/ банка</small></strong><button disabled={busy} onClick={() => void request("POST", "/api/cart/items", { sku: "honey-500", quantity })}><Plus size={15} /> В корзину</button></div></div></article>}
          {hidden && <article className="honey-product hidden-product"><Leaf size={36} /><small>ЗАКРЫТАЯ КОЛЛЕКЦИЯ</small><h4>Маточное молочко</h4><p>Позиция для внутреннего каталога. Не предназначена для публичной витрины.</p><code>hidden-queen</code></article>}
        </div>
      </section>
      <aside className="honey-basket" aria-label="Корзина"><div className="honey-basket-heading"><ShoppingBag size={20} /><h3>Ваша корзина</h3><span>{(gift ? 1 : 0) + count}</span></div>
        {!hasItems ? <div className="honey-empty-basket"><ShoppingBag size={32} /><strong>Здесь пока тихо</strong><p>Выберите мёд или подарок.<br />Мы всё аккуратно упакуем.</p></div> : <div className="honey-basket-items">{gift && <div><Gift size={23} /><span><strong>Медовое знакомство</strong><small>Подарочный набор · 1 шт.</small></span><button aria-label="Убрать подарочный набор" onClick={() => setGift(false)}><Trash2 size={14} /></button><b>4 990 ₽</b></div>}{count !== 0 && <div><Leaf size={23} /><span><strong>Липовый мёд</strong><small>500 г · {count} шт.</small></span><b>{money(honeyTotal)}</b></div>}</div>}
        <button className="honey-coupon-toggle" aria-expanded={couponOpen} onClick={() => setCouponOpen(!couponOpen)}>Есть промокод? <Plus size={14} /></button>{couponOpen && <form className="honey-coupon" onSubmit={(event) => { event.preventDefault(); void request("POST", "/api/cart/coupon", { code: coupon, cart_id: cartId }); }}><label htmlFor="honey-coupon-code">Промокод</label><div><input id="honey-coupon-code" value={coupon} onChange={(event) => setCoupon(event.target.value)} placeholder="Например, HONEY20" required /><button aria-label="Применить промокод" disabled={busy}><ArrowRight size={17} /></button></div><small>Один промокод — одно применение на корзину.</small></form>}
        {couponBody && <p className={couponResult!.status >= 400 ? "honey-inline-error" : "honey-inline-success"} role="status">{couponResult!.status >= 400 ? String(couponBody.error) : `Скидок применено: ${String(couponBody.discounts_applied)}`}</p>}
        <div className="honey-basket-total"><span>Стоимость товаров</span><strong>{money(total)}</strong></div><p className="honey-basket-note">Промокоды проверяются отдельно. Доставка и платёж — учебные.</p><button className="honey-primary honey-checkout" disabled={busy || !hasItems} onClick={() => void checkout()}>{busy ? "Подождите…" : "Оформить заказ"}<ArrowRight size={16} /></button><button className="honey-new-cart" disabled={busy} onClick={() => { setGift(false); void request("POST", "/api/cart/new"); }}>Начать новую корзину</button><small className="honey-cart-id">Корзина {cartId}</small>
      </aside>
    </div>
    {orderBody && <section className="honey-order-confirmation" aria-label="Подтверждение заказа"><PackageCheck size={32} /><div><span className="honey-eyebrow">СПАСИБО ЗА ПОКУПКУ</span><h3>Заказ {String(orderBody.order_id)} принят</h3><p>Мы уже готовим его к отправке. Подробности — в личном кабинете.</p></div><strong>Оплачено {money(Number(orderBody.charged))}</strong></section>}
  </>;
}

export function HoneyJar({ variant }: { variant: "flower" | "linden" | "buckwheat" }) {
  return <div className={`shop-jar shop-jar-${variant}`}><div className="shop-jar-lid" /><div className="shop-jar-label"><Leaf size={19} /><small>МЕДОВАЯ ЛАВКА</small><strong>{variant === "flower" ? "Цветочный" : variant === "linden" ? "Липовый" : "Гречишный"}</strong><span>С семейной пасеки · 500 г</span></div></div>;
}
