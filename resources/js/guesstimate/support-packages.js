/*--- GUESSTIMATE: PAKIETY SUPPORT ---*/
// Operacje na pakietach i punktach oferty support. Ten sam kształt danych ma `document.support` oferty (edytor dokumentu)
// i wzór w ustawieniach. Każdy punkt ma jedną komórkę na pakiet, więc zmiany pakietów przestawiają też komórki punktów.

const COLORS = ['orange', 'flame', 'indigo', 'magenta', 'violet'];
const swap = (list, index, target) => list.splice(target, 0, list.splice(index, 1)[0]);

export const supportMethods = {
  supportAddPackage(support, max) {
    if (support.packages.length >= max) return;
    const used = support.packages.map((pack) => pack.color);
    support.packages.push({
      name: '',
      color: COLORS.find((color) => !used.includes(color)) || COLORS[0],
      description: '',
      price: '',
      months: support.packages.at(-1)?.months || 1,
      price_note: support.packages.at(-1)?.price_note || '',
    });
    support.points.forEach((point) => point.cells.push({ on: false, label: '' }));
  },

  supportRemovePackage(support, index) {
    const name = support.packages[index]?.name || `Pakiet ${index + 1}`;
    if (!window.confirm(`Usunąć pakiet „${name}”? Zaznaczenia punktów tego pakietu też znikną.`)) return;
    support.packages.splice(index, 1);
    support.points.forEach((point) => {
      point.cells.splice(index, 1);
      if (point.group === index) point.group = null;
      else if (point.group !== null && point.group > index) point.group -= 1;
    });
  },

  supportMovePackage(support, index, delta) {
    const target = index + delta;
    if (target < 0 || target >= support.packages.length) return;
    swap(support.packages, index, target);
    support.points.forEach((point) => {
      swap(point.cells, index, target);
      if (point.group === index) point.group = target;
      else if (point.group === target) point.group = index;
    });
  },

  supportAddPoint(support, max) {
    if (support.points.length >= max) return;
    support.points.push({ name: '', description: '', group: null, cells: support.packages.map(() => ({ on: true, label: '' })) });
  },

  supportRemovePoint(support, index) {
    support.points.splice(index, 1);
  },

  supportMovePoint(support, index, delta) {
    const target = index + delta;
    if (target < 0 || target >= support.points.length) return;
    swap(support.points, index, target);
  },

  /** Kolumna legendy: '' = automatycznie (pierwszy pakiet z tym punktem). */
  supportSetGroup(point, value) {
    point.group = value === '' ? null : Number(value);
  },
};

/** Wzór pakietów w ustawieniach: stan w Alpine, zapis zwykłym formularzem (JSON w ukrytym polu). */
export default (config) => ({
  template: config.template,
  errors: config.errors || {},
  ...supportMethods,

  supportError(key) { return this.errors[key] || null; },
});
