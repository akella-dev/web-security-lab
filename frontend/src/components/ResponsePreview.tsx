"use client";

import { useEffect, useState } from "react";

import { buildReviewDocument, formatResponseText, type WorkbenchResponse } from "@/lib/lab";

export function ResponsePreview({ response }: { response: WorkbenchResponse | null }) {
  const [alertValue, setAlertValue] = useState("");

  useEffect(() => {
    function receive(event: MessageEvent) {
      if (event.data?.type === "lab-xss-alert") setAlertValue(String(event.data.value));
    }
    window.addEventListener("message", receive);
    return () => window.removeEventListener("message", receive);
  }, []);

  if (!response) return <div className="response-empty">Отправьте запрос — здесь появится HTTP-ответ.</div>;
  const bodyText = typeof response.body === "string" ? response.body : JSON.stringify(response.body, null, 2);

  return <section className="response-panel">
    <div className="response-status"><span className={response.status < 400 ? "status-ok" : "status-error"}>{response.status}</span><strong>HTTP response</strong><small>{response.preview}</small></div>
    <pre>{formatResponseText(response)}</pre>
    {response.preview === "html" ? <div className="html-preview"><strong>Изолированный HTML-предпросмотр</strong><iframe title="Изолированный ответ" sandbox="allow-scripts" srcDoc={buildReviewDocument(bodyText)} /></div> : null}
    {alertValue ? <div className="xss-alert"><strong>Учебный alert</strong><span>{alertValue}</span><button type="button" onClick={() => setAlertValue("")}>Закрыть</button></div> : null}
  </section>;
}
