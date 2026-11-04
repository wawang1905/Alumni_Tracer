const assert = require('assert');
const { buildCampusAddress } = require('../../app/Views/HTML/Developer/DeveloperCommon.js');

const joined = buildCampusAddress(['Misamis Oriental', 'Cagayan de Oro City', 'Lapasan']);
assert.strictEqual(joined, 'Misamis Oriental, Cagayan de Oro City, Lapasan');

const fallback = buildCampusAddress(['', 'Cagayan de Oro City', '']);
assert.strictEqual(fallback, 'Cagayan de Oro City');

console.log('Developer campus address tests passed.');
