/*--- GUESSTIMATE: STRONY A4 DOKUMENTU UMOWY ---*/
// Wspólne dla podglądu szablonu i wygenerowanej umowy. Pomiar wymaga DOM, sam podział jest w contract-pages.js.

import { paginate, parOffsets } from './contract-pages.js';

function sheet(host, regions) {
  const element = document.createElement('div');
  element.className = 'ct-sheet ct-doc';
  element.innerHTML = `<div class="ct-sheet-header">${regions.header}</div><div class="ct-sheet-body"></div><div class="ct-sheet-footer">${regions.footer}</div>`;
  host.append(element);
  return element;
}

function measure(host, regions) {
  const probe = sheet(host, regions);
  const content = probe.querySelector('.ct-sheet-body');
  const available = content.clientHeight;
  regions.nodes.forEach((node) => content.append(node.cloneNode(true)));
  content.style.flex = 'none';
  const clones = [...content.children];
  const tops = clones.map((node) => node.getBoundingClientRect().top);
  const end = content.getBoundingClientRect().bottom;

  const blocks = clones.map((node, i) => {
    const height = (tops[i + 1] ?? end) - tops[i];
    const block = { height, pageBreak: node.matches('hr.ct-break'), keepNext: node.matches('h1,h2,h3,.ct-par') };
    if (node.matches('table')) {
      block.parts = [...node.querySelector('tbody')?.rows || []].map((row) => row.getBoundingClientRect().height);
      block.head = node.tHead?.getBoundingClientRect().height || 0;
    } else if (node.matches('ul,ol')) {
      block.parts = [...node.children].map((item) => item.getBoundingClientRect().height);
    }
    return block;
  });

  probe.remove();
  return { available, blocks };
}

function slice(node, part) {
  if (part.from === undefined) return node.cloneNode(true);
  const clone = node.cloneNode(false);

  if (node.matches('table')) {
    if (node.tHead) clone.append(node.tHead.cloneNode(true));
    const body = document.createElement('tbody');
    [...node.querySelector('tbody').rows].slice(part.from, part.to).forEach((row) => body.append(row.cloneNode(true)));
    clone.append(body);
  } else {
    if (node.matches('ol')) clone.setAttribute('start', Number(node.getAttribute('start') || 1) + part.from);
    [...node.children].slice(part.from, part.to).forEach((item) => clone.append(item.cloneNode(true)));
  }

  return clone;
}

export function renderSheets(host, regions) {
  host.innerHTML = '';
  const { available, blocks } = measure(host, regions);
  const { pages, overflow } = paginate(blocks, available);
  const parts = pages.map((page) => page.map((part) => slice(regions.nodes[part.index], part)));
  const offsets = parOffsets(parts, (node) => (node.matches('.ct-par') ? 1 : 0));

  parts.forEach((page, i) => {
    const element = sheet(host, regions);
    const content = element.querySelector('.ct-sheet-body');
    content.style.counterReset = `ct-par ${offsets[i]}`;
    content.append(...page);
    element.querySelectorAll('[data-var="page.number"]').forEach((el) => { el.textContent = i + 1; });
    element.querySelectorAll('[data-var="page.count"]').forEach((el) => { el.textContent = parts.length; });
  });

  return { pages: parts.length, overflow: overflow.length };
}
