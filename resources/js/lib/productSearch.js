const SEARCH_STOP_WORDS = new Set([
    'a',
    'al',
    'con',
    'de',
    'del',
    'el',
    'en',
    'la',
    'las',
    'lo',
    'los',
    'para',
    'por',
    'un',
    'una',
    'y',
]);

export function normalizeProductSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function editDistanceWithinLimit(left, right, limit) {
    if (Math.abs(left.length - right.length) > limit) {
        return limit + 1;
    }

    let previousPrevious = null;
    let previous = Array.from({ length: right.length + 1 }, (_, index) => index);

    for (let leftIndex = 1; leftIndex <= left.length; leftIndex += 1) {
        const current = [leftIndex];
        let rowMinimum = leftIndex;

        for (let rightIndex = 1; rightIndex <= right.length; rightIndex += 1) {
            const substitutionCost =
                left[leftIndex - 1] === right[rightIndex - 1] ? 0 : 1;
            let distance = Math.min(
                previous[rightIndex] + 1,
                current[rightIndex - 1] + 1,
                previous[rightIndex - 1] + substitutionCost
            );

            if (
                previousPrevious &&
                leftIndex > 1 &&
                rightIndex > 1 &&
                left[leftIndex - 1] === right[rightIndex - 2] &&
                left[leftIndex - 2] === right[rightIndex - 1]
            ) {
                distance = Math.min(distance, previousPrevious[rightIndex - 2] + 1);
            }

            current.push(distance);
            rowMinimum = Math.min(rowMinimum, distance);
        }

        if (rowMinimum > limit) {
            return limit + 1;
        }

        previousPrevious = previous;
        previous = current;
    }

    return previous[right.length];
}

function tokenMatchScore(queryToken, productToken) {
    if (queryToken === productToken) {
        return 100;
    }
    if (productToken.startsWith(queryToken)) {
        return 82;
    }
    if (queryToken.length >= 3 && productToken.includes(queryToken)) {
        return 68;
    }
    if (queryToken.length < 4) {
        return 0;
    }

    const distanceLimit = queryToken.length >= 7 ? 2 : 1;
    const distance = editDistanceWithinLimit(queryToken, productToken, distanceLimit);
    return distance <= distanceLimit ? 48 - distance * 10 : 0;
}

export function productSearchScore(product, query) {
    const normalizedQuery = normalizeProductSearch(query);
    const queryTokens = normalizedQuery
        .split(' ')
        .filter((token) => token && !SEARCH_STOP_WORDS.has(token));

    if (!queryTokens.length) {
        return 0;
    }

    const fields = [
        { value: product.name, weight: 5 },
        { value: product.category, weight: 4 },
        { value: product.included_items, weight: 3 },
        { value: product.description, weight: 2 },
        { value: product.allergens, weight: 1 },
    ].map(({ value, weight }) => {
        const text = normalizeProductSearch(value);
        return { text, tokens: text.split(' ').filter(Boolean), weight };
    });

    let score = 0;

    for (const queryToken of queryTokens) {
        let bestFieldScore = 0;

        for (const field of fields) {
            const tokenScore = Math.max(
                0,
                ...field.tokens.map((productToken) =>
                    tokenMatchScore(queryToken, productToken)
                )
            );
            bestFieldScore = Math.max(bestFieldScore, tokenScore * field.weight);
        }

        if (bestFieldScore === 0) {
            return null;
        }

        score += bestFieldScore;
    }

    if (fields[0].text.includes(normalizedQuery)) {
        score += 60;
    } else if (fields.some((field) => field.text.includes(normalizedQuery))) {
        score += 25;
    }

    return score;
}

export function searchProducts(products, query) {
    const normalizedQuery = normalizeProductSearch(query);
    if (!normalizedQuery || normalizedQuery.split(' ').every((token) => SEARCH_STOP_WORDS.has(token))) {
        return products;
    }

    return products
        .map((product, index) => ({
            product,
            index,
            score: productSearchScore(product, normalizedQuery),
        }))
        .filter(({ score }) => score !== null)
        .sort((left, right) => right.score - left.score || left.index - right.index)
        .map(({ product }) => product);
}
