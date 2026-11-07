// Run after compiling Sass to assets/colors.raw.css. Convert palette literals in
// WordPress's compiled rules to runtime variables with native preview fallbacks.
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const postcss = require('postcss');
const selectorParser = require('postcss-selector-parser');
const file = new URL('../assets/colors.raw.css', import.meta.url);
let css = fs.readFileSync(file, 'utf8');
const slots = new Map([
    ['#f6f5f3', '--lfa-canvas'], ['#b13f32', '--lfa-action'],
    ['#8f3026', '--lfa-action-hover-20'], ['#9f382d', '--lfa-action-hover-10'],
    ['#1e1e1e', '--lfa-submenu'], ['#262626', '--lfa-nav'],
    ['#ff6f60', '--lfa-accent'], ['#d5d5d5', '--lfa-menu-text'],
    ['#ff9b90', '--lfa-accent-hover'],
    ['hsl(0,0%,21.9019607843%)', '--lfa-nav-lighter'],
    ['rgb(90%,39.1764705882%,33.8823529412%)', '--lfa-accent-muted'],
    ['rgb(61.6144078777%,21.9305519565%,17.4051999654%)', '--lfa-action-surface'],
    ['rgb(79.9602660447%,34.2696726268%,29.0593417984%)', '--lfa-action-bright'],
]);
// Do not transform existing var() fallbacks: those are already symbolic.
css = css.replace(/var\([^)]*\)|#[0-9a-f]{3,8}\b|(?:rgb|hsl)\([^)]*\)/gi, value => {
    const slot = slots.get(value);
    return slot ? `var(${slot},${value})` : value;
});
css = css.replaceAll('color:var(--lfa-action,#b13f32)', 'color:var(--lfa-link,#b13f32)');

// WordPress normally loads only the selected scheme. Scope every rule as an
// additional safeguard: a cached or duplicated stylesheet cannot repaint users
// who chose another scheme. :is() preserves selectors whose ancestors are body
// classes (for example .wp-core-ui), and pseudo-elements stay outside :is().
const scope = 'body.admin-color-leapfrog-admin';
const sheet = postcss.parse(css);
sheet.walkRules(rule => {
    if (rule.parent.type === 'atrule' && /keyframes$/i.test(rule.parent.name)) return;
    rule.selector = selectorParser(selectors => {
        selectors.each(selector => {
            if (selector.nodes.length === 1 && selector.first.type === 'pseudo' && selector.first.value === ':root') {
                selector.replaceWith(selectorParser().astSync(scope).first.clone());
                return;
            }
            const lastCombinator = selector.nodes.reduce((index, node, i) => node.type === 'combinator' ? i : index, -1);
            const body = selector.nodes.slice(lastCombinator + 1).find(node => node.type === 'tag' && node.value === 'body');
            if (body) {
                selector.insertAfter(body, selectorParser.className({ value: 'admin-color-leapfrog-admin' }));
                return;
            }
            const inner = selector.clone();
            const pseudoElements = [];
            inner.nodes.slice().forEach(node => {
                if (node.type === 'pseudo' && (/^::/.test(node.value) || /^:(before|after|first-line|first-letter)$/i.test(node.value))) {
                    pseudoElements.push(node.clone());
                    node.remove();
                }
            });
            const wrapper = selectorParser.pseudo({ value: ':is' });
            wrapper.append(inner);
            selector.removeAll();
            const scoped = selectorParser().astSync(scope).first;
            scoped.nodes.forEach(node => selector.append(node.clone()));
            selector.append(selectorParser.combinator({ value: ' ' }));
            selector.append(wrapper);
            pseudoElements.forEach(node => selector.append(node));
        });
    }).processSync(rule.selector, { lossless: false });
});
css = sheet.toString();
fs.writeFileSync(new URL('../assets/colors.min.css', import.meta.url), '/*! Leapfrog Admin. Based on WordPress 7.1.3, GPL-2.0-or-later. */\n' + css);
fs.unlinkSync(file);
