// Test NaN behavior with parseInt
const empty = parseInt("");
const valid = parseInt("5");
const p = valid;

console.log("parseInt empty:", empty, "| isNaN:", isNaN(empty));
console.log("!empty:", !empty, "| empty < 1:", empty < 1);
console.log("!empty || empty < 1:", !empty || empty < 1, "<-- should be true (catches NaN!)");

console.log("parseInt valid:", p);
console.log("!p:", !p, "| p < 1:", p < 1);
console.log("!p || p < 1:", !p || p < 1, "<-- should be false (valid pass)");
