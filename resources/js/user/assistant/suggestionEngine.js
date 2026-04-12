import { CHIP_LIBRARY_BY_LANG, clamp } from './topicLibrary';

function uniquePreserve(arr) {
    var out = [];
    var seen = new Set();
    for (var i = 0; i < arr.length; i++) {
        var v = arr[i];
        if (typeof v !== 'string') continue;
        if (!v.trim()) continue;
        if (seen.has(v)) continue;
        seen.add(v);
        out.push(v);
    }
    return out;
}

function isSoilTooWet(farmContext) {
    var s = String(farmContext && (farmContext.soil_condition || farmContext.soil || '') || '').toLowerCase();
    return s.indexOf('wet') !== -1 || s.indexOf('basa') !== -1 || s.indexOf('mud') !== -1;
}

function riskIsHigh(farmContext) {
    var r = (farmContext && farmContext.flood_risk && farmContext.flood_risk.level) ? String(farmContext.flood_risk.level).toUpperCase() : '';
    return r === 'HIGH';
}

export function getDynamicSuggestionChips(opts) {
    var topic = opts.topic || 'fallback';
    var depth = typeof opts.depth === 'number' ? opts.depth : 0;
    depth = clamp(depth, 0, 2);

    var lang = opts.lang || 'en';
    if (!CHIP_LIBRARY_BY_LANG[lang]) lang = 'en';

    var farmContext = opts.farmContext || {};
    var recent = uniquePreserve(opts.recentlyUsedChipTexts || []);
    var used = new Set(opts.usedChipTexts || []);

    var libraryForLang = CHIP_LIBRARY_BY_LANG[lang];
    var topicEntry = libraryForLang[topic] || libraryForLang.fallback;

    function filterOut(list) {
        var out = [];
        for (var i = 0; i < list.length; i++) {
            var chip = list[i];
            if (!chip || typeof chip !== 'string') continue;
            if (used.has(chip)) continue;
            if (recent.indexOf(chip) !== -1) continue;
            out.push(chip);
        }
        return out;
    }

    var candidates = [];
    if (topic === 'fallback') {
        candidates = topicEntry.base.slice();
        // Never show too many fallback prompts
    } else {
        if (depth === 0) candidates = topicEntry.base.slice();
        if (depth === 1) candidates = topicEntry.followup ? topicEntry.followup.slice() : topicEntry.base.slice();
        if (depth === 2) candidates = topicEntry.deeper ? topicEntry.deeper.slice() : (topicEntry.followup ? topicEntry.followup.slice() : topicEntry.base.slice());
    }

    // Lightweight context ordering tweaks:
    // - If soil is too wet, prefer chips about checking moisture/avoid application.
    // - If flood risk is high, prefer drainage-related watering chips (still within watering topic).
    var tooWet = isSoilTooWet(farmContext);
    var highFlood = riskIsHigh(farmContext);

    if (topic === 'watering' && tooWet) {
        candidates = candidates.slice().sort(function (a, b) {
            var aw = a.toLowerCase().indexOf('wet') !== -1 || a.toLowerCase().indexOf('basa') !== -1;
            var bw = b.toLowerCase().indexOf('wet') !== -1 || b.toLowerCase().indexOf('basa') !== -1;
            return (bw ? 1 : 0) - (aw ? 1 : 0);
        });
    }
    if (topic === 'watering' && highFlood) {
        candidates = candidates.slice().sort(function (a, b) {
            var aw = a.toLowerCase().indexOf('drain') !== -1 || a.toLowerCase().indexOf('flood') !== -1;
            var bw = b.toLowerCase().indexOf('drain') !== -1 || b.toLowerCase().indexOf('flood') !== -1;
            return (bw ? 1 : 0) - (aw ? 1 : 0);
        });
    }

    candidates = filterOut(candidates);

    // Progressive fill: if depth list is empty after filtering, fill from followup/base.
    if (candidates.length < 3) {
        var fillFrom = [];
        if (topic === 'fallback') {
            fillFrom = (topicEntry.followup || []).concat(topicEntry.deeper || []);
        } else {
            if (topicEntry.followup) fillFrom = fillFrom.concat(topicEntry.followup);
            if (topicEntry.base) fillFrom = fillFrom.concat(topicEntry.base);
            if (topicEntry.deeper) fillFrom = fillFrom.concat(topicEntry.deeper);
        }
        candidates = uniquePreserve(candidates.concat(filterOut(fillFrom)));
    }

    // Enforce max 5 and ensure at least 3 (otherwise fallback list).
    candidates = candidates.slice(0, 5);
    if (candidates.length < 3) {
        var fallbackList = filterOut(libraryForLang.fallback.base.concat(libraryForLang.fallback.followup || []));
        candidates = fallbackList.slice(0, 5);
    }

    return candidates.slice(0, 5);
}

