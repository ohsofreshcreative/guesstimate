/*--- GUESSTIMATE: PODZIAŁ SZABLONU UMOWY NA STRONY A4 ---*/
// Czysta logika bez DOM (testy: tests/js/contract-pages.test.mjs).
// Blok: { height, pageBreak?, keepNext?, parts?: number[], head?: number }. `parts` to wiersze tabeli lub punkty listy.
// Wynik to przybliżenie podziału: podgląd, a nie obietnica zgodności z Wordem. Akapitów nie dzielimy między strony.

export function paginate(blocks, pageHeight) {
  const pages = [[]];
  const overflow = [];
  let used = 0;
  const newPage = () => { pages.push([]); used = 0; };
  const current = () => pages[pages.length - 1];

  blocks.forEach((block, index) => {
    if (block.pageBreak) {
      if (current().length) newPage();
      return;
    }

    if (block.parts?.length && used + block.height > pageHeight) {
      const head = block.head || 0;
      let from = 0;
      let sum = head;

      block.parts.forEach((part, i) => {
        if (used + sum + part > pageHeight && (i > from || used > 0)) {
          if (i > from) current().push({ index, from, to: i });
          newPage();
          from = i;
          sum = head;
        }
        sum += part;
      });

      current().push({ index, from, to: block.parts.length });
      used += sum;
      if (sum > pageHeight) overflow.push(index);
      return;
    }

    const next = blocks[index + 1];
    const keep = block.keepNext && next && !next.pageBreak ? (next.parts?.[0] ?? next.height) + (next.head || 0) : 0;

    if (used > 0 && used + block.height + keep > pageHeight) newPage();

    current().push({ index });
    used += block.height;
    if (block.height > pageHeight) overflow.push(index);
  });

  return { pages, overflow };
}

// Numer § na początku każdej strony: licznik CSS nie przechodzi między osobnymi kartkami.
export function parOffsets(pages, parCount) {
  let seen = 0;

  return pages.map((page) => {
    const start = seen;
    page.forEach((slice) => { seen += parCount(slice); });
    return start;
  });
}
