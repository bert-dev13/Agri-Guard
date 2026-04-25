/**
 * Topic -> chip library (short, farmer-friendly).
 * Kept intentionally lightweight (keyword-based, no external calls).
 */

export const ASSISTANT_TOPICS = [
    'planting',
    'watering',
    'spraying',
    'crop_stage',
    'fertilizer',
    'pest_health',
    'weather_timing',
    'harvest',
    'fallback',
];

const EN = {
    planting: {
        base: [
            'Is the soil ready for planting?',
            'Should I plant this morning or afternoon?',
            'Is rain enough after planting?',
            'What should I avoid after planting?',
            'Is wind safe for sowing today?',
        ],
        followup: [
            'Should I water right after planting?',
            'Is the soil too wet to plant today?',
            'How deep should I plant seeds?',
            'Will heavy rain affect germination?',
        ],
        deeper: [
            'How can I protect seedlings from sudden rain?',
            'What is the next step after planting?',
            'When should I start light weeding?',
        ],
    },
    watering: {
        base: [
            'How much water is enough today?',
            'Will rain replace watering today?',
            'Should I skip afternoon watering?',
            'How can I check soil moisture?',
            'Am I overwatering my crop?',
        ],
        followup: [
            'Should I water again later today?',
            'Is that amount enough for this stage?',
            'Can I combine watering with fertilizer?',
            'What if only light rain falls?',
        ],
        deeper: [
            'Should I irrigate before sunset?',
            'What signs show the soil is already moist enough?',
            'Do I need to reduce watering after rain?',
        ],
    },
    spraying: {
        base: [
            'Is wind safe for spraying?',
            'Will rain wash off the spray?',
            'What time is best for spraying today?',
            'Should I delay spraying until tomorrow?',
            'Is humidity too high for spraying?',
        ],
        followup: [
            'Is leaf wetness too high right now?',
            'Should I spray this morning instead?',
            'Is drift risk high today?',
            'Should I adjust the spray plan due to rain?',
        ],
        deeper: [
            'Do I need a second application?',
            'How can I reduce spray drift near the farm edge?',
            'What is the safest timing for re-spraying?',
        ],
    },
    crop_stage: {
        base: [
            'What should I do at this crop stage?',
            'Is fertilizer needed at this stage?',
            'What problems are common right now?',
            'Is this stage sensitive to heavy rain?',
            'What should I monitor this week?',
        ],
        followup: [
            'Is this stage drought-sensitive?',
            'What is the next stage after this?',
            'Should I adjust watering at this stage?',
            'When should I start pest scouting?',
        ],
        deeper: [
            'How do I plan care for the next 7 days?',
            'What changes when the crop moves to flowering?',
            'What should I prioritize next to avoid setbacks?',
        ],
    },
    fertilizer: {
        base: [
            'Should I apply fertilizer today?',
            'Will rain affect fertilizer use?',
            'Is the soil too wet for application?',
            'What fertilizer timing is best now?',
            'Should I wait until tomorrow?',
        ],
        followup: [
            'Should I apply before or after watering?',
            'Will fertilizer wash away in rain?',
            'Is this stage too early for topdressing?',
            'Do I need a split dose instead?',
        ],
        deeper: [
            'How do I avoid nutrient burn?',
            'What should I monitor after fertilizer application?',
            'Should I adjust fertilizer if heavy rain is expected?',
        ],
    },
    pest_health: {
        base: [
            'Why are my leaves turning yellow?',
            'Is there pest risk today?',
            'What should I inspect this morning?',
            'Is disease risk higher after rain?',
            'Should I spray now or wait?',
        ],
        followup: [
            'What signs should I check first?',
            'Is this likely nutrient stress or pest damage?',
            'Are wet leaves increasing disease risk?',
            'Do I need to remove infected leaves?',
        ],
        deeper: [
            'What is the best next action for crop recovery?',
            'How often should I monitor pests?',
            'When is the safest time to spray again?',
        ],
    },
    weather_timing: {
        base: [
            'Is this morning safer than afternoon?',
            'Will rain affect field work later?',
            'What time is safest to work today?',
            'Should I finish field work before noon?',
            'Is wind expected to increase later?',
        ],
        followup: [
            'Should I schedule heavy tasks after rain ends?',
            'What’s the best window for field checks?',
            'Should I switch tasks due to wind changes?',
            'How will weather shift by evening?',
        ],
        deeper: [
            'Can I spread field work into 2 short sessions?',
            'What should I prioritize before it gets windy?',
            'How do I reduce weather-related delays?',
        ],
    },
    harvest: {
        base: [
            'Is today safe for harvesting?',
            'Will rain affect harvest quality?',
            'Should I harvest this morning instead?',
            'Is the field too wet for harvest work?',
            'What should I prepare after harvesting?',
        ],
        followup: [
            'How can I protect harvested crops from rain?',
            'Should I delay harvesting if rain returns?',
            'What is the safest timing for cutting?',
            'Do I need to dry produce longer?',
        ],
        deeper: [
            'How do I reduce post-harvest losses?',
            'When should I sort and store harvested crops?',
            'What to do if harvest quality drops?',
        ],
    },
    fallback: {
        base: [
            'Can I plant today?',
            'Do I need to water today?',
            'Is spraying safe today?',
            'How strong is the rain chance today?',
            'What should I do at my crop stage?',
        ],
        followup: [
            'What should I check first in the morning?',
            'Should I focus on drainage or watering today?',
            'What should I avoid due to rain?',
        ],
        deeper: [
            'What is the safest plan for the next 24 hours?',
            'What should I monitor based on my crop stage?',
        ],
    },
};

// Taglish is a close approximation for this project.
const TAG = {
    planting: {
        base: [
            'Ready ba ang lupa for planting?',
            'Umaga o hapon magtanim ngayon?',
            'Enough ba ang ulan pagkatapos magtanim?',
            'Ano dapat iwas pagkatapos magtanim?',
            'Safe ba ang hangin for sowing today?',
        ],
        followup: [
            'Dapat ba akong magdidilig right after planting?',
            'Masyado bang basa ang lupa para magtanim ngayon?',
            'Gaano kalalim dapat itanim?',
            'Makakaapekto ba ang bigat na ulan sa pagtubo?',
        ],
        deeper: [
            'Paano protektahan ang seedlings sa biglang ulan?',
            'Ano next step pagkatapos magtanim?',
            'Kailan start ang light weeding?',
        ],
    },
    watering: {
        base: [
            'Sakto ba ang tubig today?',
            'Papalitan ba ng ulan ang pagdidilig?',
            'I-skip ba ang afternoon watering?',
            'Paano i-check ang soil moisture?',
            'Overwatering ba ako?',
        ],
        followup: [
            'Magdidilig ulit ba mamaya ngayon?',
            'Tama ba ang dami para sa stage na ito?',
            'Pwede ba pagsabayin ang pataba at pagdidilig?',
            'Ano kung light rain lang ang darating?',
        ],
        deeper: [
            'Dapat ba bago mag-sunset mag-irrigate?',
            'Anong signs na sapat na ang basa ng lupa?',
            'Dapat ko bang bawasan ang watering after rain?',
        ],
    },
    spraying: {
        base: [
            'Safe ba ang wind for spraying?',
            'Huhugasan ba ng ulan ang spray?',
            'Anong oras best mag-spray today?',
            'I-delay ba ang spray bukas?',
            'Mataas ba ang humidity para sa spray?',
        ],
        followup: [
            'Masyado bang basa ang dahon ngayon?',
            'Mas okay bang umaga mag-spray?',
            'High ba ang drift risk today?',
            'Kailangan ko ba i-adjust dahil sa ulan?',
        ],
        deeper: [
            'Kailangan ba ng second application?',
            'Paano bawasan ang spray drift sa gilid ng farm?',
            'Kailan safest timing for re-spraying?',
        ],
    },
    crop_stage: {
        base: [
            'Ano dapat gawin sa crop stage na ito?',
            'Kailangan ba ng pataba sa stage na ito?',
            'Ano ang common problems ngayon?',
            'Sensitive ba sa heavy rain ang stage na ito?',
            'Ano i-monitor ko ngayong linggo?',
        ],
        followup: [
            'Sensitive ba ito sa drought?',
            'Ano next stage pagkatapos nito?',
            'Dapat ko bang i-adjust ang watering?',
            'Kailan start ang pest scouting?',
        ],
        deeper: [
            'Paano planuhin ang care sa susunod na 7 araw?',
            'Ano ang nagbabago pag nag-flowering na?',
            'Ano ang priority para hindi ma-delay?',
        ],
    },
    fertilizer: {
        base: [
            'Mag-pataba ba ngayon?',
            'Nakakaapekto ba ang ulan sa pataba?',
            'Masyado bang basa ang lupa para mag-apply?',
            'Kailan best maglagay ng pataba?',
            'Maghintay na lang ba hanggang bukas?',
        ],
        followup: [
            'Mag-apply ba before o after watering?',
            'Huhugasan ba ng ulan ang pataba?',
            'Maaga pa ba para sa topdressing?',
            'Split dose ba ang kailangan?',
        ],
        deeper: [
            'Paano iwas nutrient burn?',
            'Ano i-monitor after maglagay ng pataba?',
            'Dapat ba i-adjust kung tuloy-tuloy ang ulan?',
        ],
    },
    pest_health: {
        base: [
            'Bakit dilaw ang dahon?',
            'May pest risk ba today?',
            'Ano i-check ko ngayong umaga?',
            'Mas mataas ba ang disease risk after rain?',
            'Mag-spray na ba o maghintay?',
        ],
        followup: [
            'Ano sign ang i-check muna?',
            'Nutrient stress ba o pest damage?',
            'Tumataas ba ang disease risk sa basa ng dahon?',
            'Kailangan bang tanggalin ang infected leaves?',
        ],
        deeper: [
            'Ano next action para sa crop recovery?',
            'Gaano kadalas mag-monitor ng pests?',
            'Kailan safest time ulit mag-spray?',
        ],
    },
    weather_timing: {
        base: [
            'Mas safe ba umaga kaysa hapon?',
            'Nakakaapekto ba ang ulan sa later na work?',
            'Anong oras safest mag-work today?',
            'Dapat tapusin bago tanghali?',
            'Tataas ba ang hangin later?',
        ],
        followup: [
            'Dapat ba schedule mabigat na tasks after rain?',
            'Ano best window for field checks?',
            'Papalitan ba ang tasks dahil sa wind?',
            'Paano magbabago ang weather by evening?',
        ],
        deeper: [
            'Pwede bang hatiin ang work into 2 short sessions?',
            'Ano priority bago lumakas ang hangin?',
            'Paano iwas delays dahil sa panahon?',
        ],
    },
    harvest: {
        base: [
            'Safe ba ngayon for harvesting?',
            'Makakaapekto ba ang ulan sa ani?',
            'Mas okay ba umaga mag-harvest?',
            'Basa ba ang lupa para sa harvest work?',
            'Ano i-prepare after harvesting?',
        ],
        followup: [
            'Paano protektahan ang ani sa ulan?',
            'Dapat ba i-delay kung babalik ang ulan?',
            'Ano safest timing for cutting?',
            'Kailangan ba ng mas mahabang pagpatuyo?',
        ],
        deeper: [
            'Paano iwas post-harvest losses?',
            'Kailan i-sort at i-store ang ani?',
            'Ano gagawin kung bumaba ang kalidad?',
        ],
    },
    fallback: {
        base: [
            'Pwede ba akong magtanim today?',
            'Kailangan ko bang magdidilig today?',
            'Safe ba ang spray today?',
            'Gaano kataas ang chance ng ulan ngayon?',
            'Ano next step sa crop stage ko?',
        ],
        followup: [
            'Ano dapat i-check muna sa umaga?',
            'Drainage o watering ang unahin ko today?',
            'Ano dapat iwas dahil sa ulan?',
        ],
        deeper: [
            'Ano safest plan sa susunod na 24 hours?',
            'Ano i-monitor base sa crop stage?',
        ],
    },
};

export const CHIP_LIBRARY_BY_LANG = {
    en: EN,
    taglish: TAG,
    tl: TAG,
};

export function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
}

