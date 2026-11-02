"use client";

import { Check, ChevronRight, ShieldCheck } from "lucide-react";

import { groupMissions, type Mission, progressOf } from "@/lib/lab";

export function MissionRail({ missions, selected, onSelect }: { missions: Mission[]; selected: string; onSelect: (slug: string) => void }) {
  const progress = progressOf(missions);
  return <aside className="course-rail">
    <div className="course-progress"><div><span>Программа курса</span><strong>{progress.completed} / {progress.total}</strong></div><i><b style={{ width: `${progress.percent}%` }} /></i><small>{progress.percent}% завершено</small></div>
    <nav aria-label="Задания курса">{groupMissions(missions).map((module, moduleIndex) => <section key={module.name} className="course-module">
      <header><span>0{moduleIndex + 1}</span><div><strong>{module.name}</strong><small>{module.missions.filter((item) => item.status === "completed").length} из {module.missions.length}</small></div></header>
      {module.missions.map((mission) => <button type="button" key={mission.slug} className={selected === mission.slug ? "course-task active" : "course-task"} onClick={() => onSelect(mission.slug)}>
        <span className={mission.status === "completed" ? "task-number done" : "task-number"}>{mission.status === "completed" ? <Check size={13} /> : mission.number}</span>
        <span><strong>{mission.title}</strong><small>{mission.difficulty} · {mission.estimated_minutes} мин</small></span>
        <ChevronRight size={15} />
      </button>)}
    </section>)}</nav>
    <footer><ShieldCheck size={16} /> Вся активность изолирована внутри учебного движка</footer>
  </aside>;
}
