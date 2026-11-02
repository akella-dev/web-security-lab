"use client";

import { useEffect } from "react";

import { buildReviewDocument } from "@/lib/lab";

export function SafeReviewFrame({ content, onAlert }: { content: string; onAlert: (value: string) => void }) {
  useEffect(() => {
    function handleMessage(event: MessageEvent) {
      if (event.data?.type === "lab-xss-alert") onAlert(String(event.data.value));
    }
    window.addEventListener("message", handleMessage);
    return () => window.removeEventListener("message", handleMessage);
  }, [onAlert]);

  return <iframe className="review-frame" sandbox="allow-scripts" srcDoc={buildReviewDocument(content)} title="Содержимое учебного отзыва" />;
}
