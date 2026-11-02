import assert from "node:assert/strict";
import test from "node:test";

import { buildReviewDocument, formatResponseText, groupMissions, parseEditorJson, progressOf, requestDraftOf } from "./lab.ts";

test("progressOf counts only server-completed missions", () => {
  const progress = progressOf([
    { status: "completed" },
    { status: "pending" },
    { status: "completed" },
    { status: "pending" },
  ]);

  assert.deepEqual(progress, { completed: 2, total: 4, percent: 50 });
});

test("buildReviewDocument isolates executable review content from the parent app", () => {
  const document = buildReviewDocument('<img src=x onerror="alert(1)">');

  assert.match(document, /default-src 'none'/);
  assert.match(document, /connect-src 'none'/);
  assert.match(document, /window\.alert/);
  assert.match(document, /lab-xss-alert/);
  assert.match(document, /<img src=x onerror="alert\(1\)">/);
});

test("groupMissions preserves teaching order and groups by topic", () => {
  const missions = [
    { module: "HTTP", number: 1 },
    { module: "HTTP", number: 2 },
    { module: "Доступ", number: 3 },
  ];

  assert.deepEqual(groupMissions(missions), [
    { name: "HTTP", missions: missions.slice(0, 2) },
    { name: "Доступ", missions: missions.slice(2) },
  ]);
});

test("requestDraftOf formats headers and body for editable request", () => {
  const draft = requestDraftOf({
    method: "POST",
    path: "/api/cart/items",
    headers: { "X-Lab": "yes" },
    body: { quantity: 1 },
    mode: "single",
  });

  assert.equal(draft.headers, '{\n  "X-Lab": "yes"\n}');
  assert.equal(draft.body, '{\n  "quantity": 1\n}');
});

test("stage preparation preserves the safe request without mutating its materials", () => {
  const request = { method: "POST", path: "/api/reset/verify", headers: {}, body: { email: "alice@example.test", token: "" }, mode: "single" as const };
  const draft = requestDraftOf(request);
  draft.body = '{"token":"changed"}';
  assert.equal(request.body.token, "");
  assert.equal(draft.path, request.path);
  assert.equal(draft.headers, "{}");
});

test("requestDraftOf normalizes legacy empty header arrays to a JSON object", () => {
  const draft = requestDraftOf({
    method: "GET",
    path: "/api/example",
    headers: [],
    body: {},
    mode: "single",
  });

  assert.equal(draft.headers, "{}");
  assert.deepEqual(parseEditorJson(draft.headers, "Заголовки"), {});
});

test("requestDraftOf normalizes legacy empty body arrays to a JSON object", () => {
  const draft = requestDraftOf({
    method: "GET",
    path: "/api/catalog?filter=honey",
    headers: {},
    body: [],
    mode: "single",
  });

  assert.equal(draft.body, "{}");
  assert.deepEqual(parseEditorJson(draft.body, "Тело"), {});
});

test("parseEditorJson accepts objects and rejects arrays", () => {
  assert.deepEqual(parseEditorJson('{"role":"admin"}', "Заголовки"), { role: "admin" });
  assert.throws(() => parseEditorJson('[1,2]', "Тело"), /Тело должно быть JSON-объектом/);
});

test("formatResponseText separates headers and JSON body with real line breaks", () => {
  const text = formatResponseText({
    status: 201,
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "X-Virtual-Lab": "true",
    },
    body: { order_id: "LAB-2048", charged: 4990, status: "created" },
    earned_challenges: [],
    preview: "json",
  });

  assert.equal(text, 'Content-Type: application/json; charset=utf-8\nX-Virtual-Lab: true\n\n{\n  "order_id": "LAB-2048",\n  "charged": 4990,\n  "status": "created"\n}');
  assert.equal(text.includes("\\n"), false);
});
