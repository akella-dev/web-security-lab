import type { Metadata } from "next";
import { KnowledgeBase } from "@/components/KnowledgeBase";

export const metadata: Metadata = { title: "База знаний — Web Security Lab" };

export default function HandbookPage() {
  return <KnowledgeBase />;
}
