/**
 * Lightweight topic classifier for AgriGuard Assistant chips.
 * Uses keyword scoring + continuity from previous detected topic.
 */

const OFFTOPIC_PATTERNS = [
    // Personal / unrelated
    'my name',
    'name?',
    'name ',
    'what is my name',
    'what\'s my name',
    'ano pangalan',
    'pangalang',
    'age',
    'birthday',
    'birthday',
    'how are you',
    'kumusta',
    'who are you',
    'taga saan',
];

const TOPIC_KEYWORDS = {
    planting: [
        'plant',
        'planting',
        'sow',
        'sowing',
        'seed',
        'seeds',
        'transplant',
        'tanim',
        'magitanim',
        'pagtatanim',
        'taniman',
        'sowing',
        'soil ready',
        'ready for planting',
    ],
    watering: [
        'water',
        'watering',
        'irrigation',
        'irrigate',
        'didig',
        'didilig',
        'pagdidilig',
        'tubig',
        'soil moisture',
        'moisture',
        'dry',
        'overwatering',
        'over water',
        'sobra tubig',
        'sobra',
        'enough water',
        'skip',
        'skip watering',
        'rain replace',
        'rain replace watering',
        'replace watering',
    ],
    spraying: [
        'spray',
        'spraying',
        'pesticide',
        'fungicide',
        'herbicide',
        'foliar',
        'mag-spray',
        'mag spray',
        'spraying safe',
        'wind safe',
        'wind',
        'humidity',
        'drift',
        'drift risk',
        'rain wash',
        'wash off',
        'huhugasan',
    ],
    crop_stage: [
        'crop stage',
        'growth stage',
        'current stage',
        'stage',
        'yugto',
        'vegetative',
        'flowering',
        'maturity',
        'early growth',
        'current crop',
        'what should i do at this stage',
        'what should i do at this crop stage',
    ],
    fertilizer: [
        'fertilizer',
        'fertilize',
        'pataba',
        'abono',
        'nutrient',
        'topdress',
        'top dressing',
        'npk',
        'feeding',
        'mag-pataba',
        'pataba',
        'fertilizer timing',
    ],
    pest_health: [
        'pest',
        'pests',
        'insect',
        'insects',
        'disease',
        'yellow',
        'dilaw',
        'brown spots',
        'spots',
        'wilt',
        'damage',
        'leaves turning',
        'leaf',
        'dahon',
        'crop health',
    ],
    weather_timing: [
        'today',
        'tomorrow',
        'this morning',
        'this afternoon',
        'this evening',
        'morning',
        'afternoon',
        'evening',
        'bukas',
        'umaga',
        'hapon',
        'gabi',
        'later',
        'timing',
        'best time',
        'oras',
    ],
    harvest: [
        'harvest',
        'harvesting',
        'ready to harvest',
        'ani',
        'pag-ani',
        'cutting',
        'cut',
        'post-harvest',
        'after harvesting',
        'ani na',
        'mangani',
    ],
};

const TIME_CONTINUATION = [
    'later',
    'tomorrow',
    'today',
    'this afternoon',
    'this morning',
    'this evening',
    'morning',
    'afternoon',
    'evening',
    'bukas',
    'umaga',
    'hapon',
    'gabi',
    'again',
    'skip',
    'should i',
];

function normalizeText(s) {
    return String(s || '')
        .toLowerCase()
        .replace(/[\u2019']/g, '')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function scoreTopic(text, keywords) {
    var score = 0;
    if (!text) return score;
    for (var i = 0; i < keywords.length; i++) {
        var k = keywords[i];
        if (!k) continue;
        if (text.indexOf(k) !== -1) score += 1;
    }
    return score;
}

function getDepthLevel(topic, text) {
    var t = text || '';
    if (!topic) return 0;
    var lvl = 0;

    function hasAny(list) {
        for (var i = 0; i < list.length; i++) {
            if (t.indexOf(list[i]) !== -1) return true;
        }
        return false;
    }

    // Depth 1 = more specific follow-up
    // Depth 2 = very specific / action-timing / quantities / recovery
    if (topic === 'watering') {
        if (hasAny(['rain replace', 'replace watering', 'skip afternoon', 'afternoon', 'soil moisture', 'overwatering', 'too wet', 'check soil moisture'])) lvl = Math.max(lvl, 1);
        if (hasAny(['how much', 'enough', 'again', 'later', 'before sunset', 'mm', 'reduce watering', 'light rain'])) lvl = Math.max(lvl, 2);
    } else if (topic === 'planting') {
        if (hasAny(['morning or afternoon', 'avoid', 'wind safe', 'rain enough', 'soil too wet', 'protect seedlings'])) lvl = Math.max(lvl, 1);
        if (hasAny(['how deep', 'right after planting', 'germination', 'start light weeding', 'water right after'])) lvl = Math.max(lvl, 2);
    } else if (topic === 'spraying') {
        if (hasAny(['wind safe', 'rain wash', 'humidity', 'leaf wetness', 'delay', 'tomorrow', 'drift'])) lvl = Math.max(lvl, 1);
        if (hasAny(['second application', 're-spraying', 'safest timing', 'spray again'])) lvl = Math.max(lvl, 2);
    } else if (topic === 'crop_stage') {
        if (hasAny(['fertilizer needed', 'common problems', 'monitor', 'sensitive', 'this stage'])) lvl = Math.max(lvl, 1);
        if (hasAny(['next stage', 'adjust watering', 'next 7 days', 'flowering', 'prioritize'])) lvl = Math.max(lvl, 2);
    } else if (topic === 'fertilizer') {
        if (hasAny(['fertilizer', 'apply fertilizer', 'pataba', 'topdress', 'timing', 'too wet'])) lvl = Math.max(lvl, 1);
        if (hasAny(['before or after watering', 'wash away', 'split dose', 'nutrient burn', 'monitor after'])) lvl = Math.max(lvl, 2);
    } else if (topic === 'pest_health') {
        if (hasAny(['yellow', 'disease risk', 'inspect', 'spray now', 'pest risk'])) lvl = Math.max(lvl, 1);
        if (hasAny(['wet leaves', 'remove infected', 'crop recovery', 'monitor pests', 'spray again'])) lvl = Math.max(lvl, 2);
    } else if (topic === 'weather_timing') {
        if (hasAny(['this morning', 'this afternoon', 'this evening', 'afternoon', 'morning', 'best time'])) lvl = Math.max(lvl, 1);
        if (hasAny(['schedule', 'two short sessions', 'heavy tasks after rain ends', 'reduce weather-related delays'])) lvl = Math.max(lvl, 2);
    } else if (topic === 'harvest') {
        if (hasAny(['harvesting', 'harvest quality', 'field too wet', 'prepare after'])) lvl = Math.max(lvl, 1);
        if (hasAny(['protect', 'delay harvesting', 'post-harvest', 'reduce post-harvest losses', 'safe timing for cutting'])) lvl = Math.max(lvl, 2);
    }

    return lvl;
}

export function detectAssistantTopic(opts) {
    var userMessage = normalizeText(opts.userMessage);
    var assistantMessage = normalizeText(opts.assistantMessage);
    var combined = normalizeText(userMessage + ' ' + assistantMessage);

    var prevTopic = opts.previousTopic || 'fallback';
    var previousDepth = opts.previousDepth || 0;

    for (var i = 0; i < OFFTOPIC_PATTERNS.length; i++) {
        var p = OFFTOPIC_PATTERNS[i];
        if (combined.indexOf(normalizeText(p)) !== -1) {
            return { topic: 'fallback', confidence: 0.95, depthLevel: 0, offTopic: true };
        }
    }

    var scores = {};
    var maxScore = 0;
    Object.keys(TOPIC_KEYWORDS).forEach(function (topic) {
        var s = scoreTopic(combined, TOPIC_KEYWORDS[topic]);
        scores[topic] = s;
        maxScore = Math.max(maxScore, s);
    });

    var bestTopic = 'fallback';
    var bestScore = 0;
    Object.keys(scores).forEach(function (topic) {
        var s = scores[topic];
        if (s > bestScore) {
            bestScore = s;
            bestTopic = topic;
        }
    });

    // If no topic keywords found, use continuity only when it looks like follow-up.
    var continuation = TIME_CONTINUATION.some(function (k) { return combined.indexOf(k) !== -1; });

    // Confidence heuristic: require at least 2 keyword matches or strong continuity.
    var confidence = maxScore >= 2 ? 0.72 : (maxScore === 1 ? 0.45 : 0.1);

    var depthLevel = getDepthLevel(bestTopic, combined);

    if (!combined || maxScore === 0) {
        if (continuation && prevTopic && prevTopic !== 'fallback') {
        return { topic: prevTopic, confidence: 0.48, depthLevel: 1, offTopic: false };
        }
        return { topic: 'fallback', confidence: 0.4, depthLevel: 0, offTopic: false };
    }

    if (confidence < 0.45 && prevTopic && prevTopic !== 'fallback' && continuation) {
        // Likely a follow-up without keywords: keep continuity.
        return { topic: prevTopic, confidence: 0.5, depthLevel: 1, offTopic: false };
    }

    // Weather timing is often an attribute, not the main action.
    // If another topic is present, prefer it.
    var bestIsTiming = bestTopic === 'weather_timing';
    if (bestIsTiming && maxScore >= 2) {
        // Keep as timing only if it was the clear winner.
        // Otherwise default to prevTopic if user seems like a continuation.
        if (prevTopic && prevTopic !== 'fallback' && continuation && previousDepth >= 1) {
        return { topic: prevTopic, confidence: 0.5, depthLevel: 1, offTopic: false };
        }
    }

    if (bestScore === 0) {
        return { topic: 'fallback', confidence: 0.4, depthLevel: 0, offTopic: false };
    }

    // Crop stage often comes with fertilizer/watering. If both appear, choose higher score.
    return { topic: bestTopic, confidence: Math.min(0.9, 0.55 + (bestScore * 0.12)), depthLevel: depthLevel, offTopic: false };
}

