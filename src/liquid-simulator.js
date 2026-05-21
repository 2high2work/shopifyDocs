/**
 * Liquid Simulator / JS Interpreter
 * Designed for shopify-web learning sandbox.
 * Supports basic variables, nested properties, filters, {% for %} loops, {% if %} conditionals, and {% assign %}.
 */

class LiquidSimulator {
    constructor() {
        this.resetContext();
    }

    resetContext() {
        this.context = {
            shop: {
                name: "Shopify Sandbox",
                domain: "sandbox.shopify.com",
                currency: "USD"
            },
            customer: {
                name: "John Doe",
                email: "john@shopify-learner.com",
                order_count: 5,
                total_spent: 120.50,
                is_logged_in: true
            },
            product: {
                title: "Shopify Retro Cap",
                price: 29.99,
                compare_at_price: 39.99,
                available: true,
                tags: ["accessories", "clothing", "retro"],
                type: "Apparel",
                vendor: "Shopify Gear"
            },
            cart: {
                item_count: 3,
                total_price: 89.97,
                items: [
                    { title: "Shopify Retro Cap", price: 29.99, quantity: 2 },
                    { title: "Liquid Cheat Sheet", price: 29.99, quantity: 1 }
                ]
            },
            collections: {
                frontpage: {
                    title: "Featured Products",
                    products: [
                        { title: "Sleek Dark Mug", price: 14.99, available: true, tags: ["kitchen", "dark"] },
                        { title: "Liquid Developer Tee", price: 24.99, available: true, tags: ["clothing", "tee"] },
                        { title: "Shopify Sticker Pack", price: 4.99, available: false, tags: ["misc"] }
                    ]
                }
            }
        };
    }

    // Resolves dot-notation variables inside the local context
    resolveValue(varName, localContext = {}) {
        varName = varName.trim();
        
        // Handle literal numbers or strings
        if (varName.startsWith("'") && varName.endsWith("'")) {
            return varName.slice(1, -1);
        }
        if (varName.startsWith('"') && varName.endsWith('"')) {
            return varName.slice(1, -1);
        }
        if (!isNaN(varName) && varName !== '') {
            return parseFloat(varName);
        }
        if (varName === 'true') return true;
        if (varName === 'false') return false;

        const parts = varName.split('.');
        let current = localContext.hasOwnProperty(parts[0]) ? localContext[parts[0]] : this.context[parts[0]];

        for (let i = 1; i < parts.length; i++) {
            if (current === null || current === undefined) return "";
            current = current[parts[i]];
        }
        return current !== undefined ? current : "";
    }

    // Applies filters such as upcase, money, size, etc.
    applyFilter(value, filterName, argsStr = '', localContext = {}) {
        filterName = filterName.trim();
        
        // Parse arguments
        let args = [];
        if (argsStr.trim()) {
            args = argsStr.split(',').map(arg => {
                arg = arg.trim();
                return this.resolveValue(arg, localContext);
            });
        }

        switch (filterName) {
            case 'upcase':
                return String(value).toUpperCase();
            case 'downcase':
                return String(value).toLowerCase();
            case 'append':
                return String(value) + (args[0] || '');
            case 'prepend':
                return (args[0] || '') + String(value);
            case 'replace':
                return String(value).split(args[0] || '').join(args[1] || '');
            case 'size':
                if (Array.isArray(value) || typeof value === 'string') {
                    return value.length;
                }
                if (typeof value === 'object' && value !== null) {
                    return Object.keys(value).length;
                }
                return 0;
            case 'first':
                return Array.isArray(value) ? value[0] : "";
            case 'last':
                return Array.isArray(value) ? value[value.length - 1] : "";
            case 'join':
                return Array.isArray(value) ? value.join(args[0] || ' ') : value;
            case 'money':
                const num = parseFloat(value);
                return isNaN(num) ? value : `$${num.toFixed(2)}`;
            case 'money_with_currency':
                const numCurr = parseFloat(value);
                const currency = this.context.shop.currency || 'USD';
                return isNaN(numCurr) ? value : `$${numCurr.toFixed(2)} ${currency}`;
            default:
                return value; // unknown filter, ignore
        }
    }

    // Parses a single output block like {{ product.price | money }}
    parseOutput(expression, localContext = {}) {
        const parts = expression.split('|');
        let value = this.resolveValue(parts[0], localContext);

        for (let i = 1; i < parts.length; i++) {
            const filterPart = parts[i].trim();
            const colonIndex = filterPart.indexOf(':');
            let filterName = filterPart;
            let argsStr = '';
            
            if (colonIndex !== -1) {
                filterName = filterPart.substring(0, colonIndex);
                argsStr = filterPart.substring(colonIndex + 1);
            }
            value = this.applyFilter(value, filterName, argsStr, localContext);
        }

        return value === null || value === undefined ? "" : value;
    }

    // Helper to evaluate conditions for {% if %} tags
    evaluateCondition(leftStr, op, rightStr, localContext) {
        const left = this.resolveValue(leftStr, localContext);
        const right = this.resolveValue(rightStr, localContext);

        switch (op) {
            case '==': return left == right;
            case '!=': return left != right;
            case '>': return left > right;
            case '<': return left < right;
            case '>=': return left >= right;
            case '<=': return left <= right;
            case 'contains': 
                if (typeof left === 'string') return left.includes(String(right));
                if (Array.isArray(left)) return left.includes(right);
                return false;
            default: return !!left;
        }
    }

    // Main render loop
    render(template, localContext = {}) {
        let output = "";
        let index = 0;
        
        while (index < template.length) {
            // 1. Look for outputs {{ ... }} or tags {% ... %}
            const nextOutputStart = template.indexOf("{{", index);
            const nextTagStart = template.indexOf("{%", index);

            // If neither remains, append rest of template and exit
            if (nextOutputStart === -1 && nextTagStart === -1) {
                output += template.substring(index);
                break;
            }

            // Decide which token appears first
            if (nextOutputStart !== -1 && (nextTagStart === -1 || nextOutputStart < nextTagStart)) {
                // We have an output block {{ ... }} first
                output += template.substring(index, nextOutputStart);
                const end = template.indexOf("}}", nextOutputStart);
                if (end === -1) {
                    // Mismatched braces, treat as normal text
                    output += "{{";
                    index = nextOutputStart + 2;
                    continue;
                }
                const expression = template.substring(nextOutputStart + 2, end).trim();
                output += this.parseOutput(expression, localContext);
                index = end + 2;
            } else {
                // We have a tag block {% ... %} first
                output += template.substring(index, nextTagStart);
                const end = template.indexOf("%}", nextTagStart);
                if (end === -1) {
                    // Mismatched braces
                    output += "{%";
                    index = nextTagStart + 2;
                    continue;
                }
                const tagContent = template.substring(nextTagStart + 2, end).trim();
                index = end + 2;

                // Handle Tags
                if (tagContent.startsWith("assign ")) {
                    // {% assign my_var = product.title %}
                    const assignMatch = tagContent.match(/^assign\s+([a-zA-Z0-9_-]+)\s*=\s*(.+)$/);
                    if (assignMatch) {
                        const varName = assignMatch[1];
                        const expr = assignMatch[2];
                        localContext[varName] = this.parseOutput(expr, localContext);
                    }
                } 
                else if (tagContent.startsWith("for ")) {
                    // {% for item in collection %}
                    const forMatch = tagContent.match(/^for\s+([a-zA-Z0-9_-]+)\s+in\s+([a-zA-Z0-9_\.-]+)$/);
                    if (forMatch) {
                        const iterVarName = forMatch[1];
                        const listVarName = forMatch[2];
                        const list = this.resolveValue(listVarName, localContext);

                        // Find corresponding endfor
                        let forCount = 1;
                        let searchIndex = index;
                        let endforIndex = -1;
                        
                        while (searchIndex < template.length) {
                            const nextFor = template.indexOf("{% for ", searchIndex);
                            const nextEndFor = template.indexOf("{% endfor %}", searchIndex);
                            const nextEndForCompact = template.indexOf("{% endfor%}", searchIndex);
                            
                            let actualEndFor = -1;
                            if (nextEndFor !== -1 && (nextEndForCompact === -1 || nextEndFor < nextEndForCompact)) {
                                actualEndFor = nextEndFor;
                            } else {
                                actualEndFor = nextEndForCompact;
                            }

                            if (actualEndFor === -1) break;

                            if (nextFor !== -1 && nextFor < actualEndFor) {
                                forCount++;
                                searchIndex = nextFor + 7;
                            } else {
                                forCount--;
                                if (forCount === 0) {
                                    endforIndex = actualEndFor;
                                    break;
                                }
                                searchIndex = actualEndFor + 11;
                            }
                        }

                        if (endforIndex !== -1) {
                            const innerTemplate = template.substring(index, endforIndex);
                            index = endforIndex + 12; // skip {% endfor %}

                            if (Array.isArray(list)) {
                                for (let i = 0; i < list.length; i++) {
                                    const loopContext = { ...localContext };
                                    loopContext[iterVarName] = list[i];
                                    loopContext['forloop'] = {
                                        length: list.length,
                                        index: i + 1,
                                        index0: i,
                                        rindex: list.length - i,
                                        rindex0: list.length - i - 1,
                                        first: i === 0,
                                        last: i === list.length - 1
                                    };
                                    output += this.render(innerTemplate, loopContext);
                                }
                            }
                        }
                    }
                } 
                else if (tagContent.startsWith("if ")) {
                    // Simplistic If block supporting: {% if left op right %}
                    // ops: ==, !=, >, <, >=, <=, contains
                    const ifMatch = tagContent.match(/^if\s+([a-zA-Z0-9_\.-]+)(?:\s*(==|!=|>|<|>=|<=|contains)\s*([a-zA-Z0-9_\.-]+|'.*'|".*"))?$/);
                    
                    // Find matching {% endif %} and any {% else %}
                    let ifCount = 1;
                    let searchIndex = index;
                    let endifIndex = -1;
                    let elseIndex = -1;

                    while (searchIndex < template.length) {
                        const nextIf = template.indexOf("{% if ", searchIndex);
                        const nextElse = template.indexOf("{% else %}", searchIndex);
                        const nextElseCompact = template.indexOf("{% else%}", searchIndex);
                        const nextEndIf = template.indexOf("{% endif %}", searchIndex);
                        const nextEndIfCompact = template.indexOf("{% endif%}", searchIndex);

                        let actualEndIf = nextEndIf !== -1 ? nextEndIf : nextEndIfCompact;
                        let actualElse = nextElse !== -1 ? nextElse : nextElseCompact;

                        if (actualEndIf === -1) break;

                        if (nextIf !== -1 && nextIf < actualEndIf) {
                            ifCount++;
                            searchIndex = nextIf + 6;
                        } else {
                            if (actualElse !== -1 && actualElse < actualEndIf && ifCount === 1) {
                                elseIndex = actualElse;
                            }
                            ifCount--;
                            if (ifCount === 0) {
                                endifIndex = actualEndIf;
                                break;
                            }
                            searchIndex = actualEndIf + 11;
                        }
                    }

                    if (endifIndex !== -1) {
                        let isTrue = false;
                        if (ifMatch) {
                            const left = ifMatch[1];
                            const op = ifMatch[2];
                            const right = ifMatch[3];
                            isTrue = this.evaluateCondition(left, op, right, localContext);
                        }

                        let truthyTemplate = "";
                        let falsyTemplate = "";

                        if (elseIndex !== -1) {
                            truthyTemplate = template.substring(index, elseIndex);
                            falsyTemplate = template.substring(elseIndex + 10, endifIndex); // skip {% else %} (10 chars)
                        } else {
                            truthyTemplate = template.substring(index, endifIndex);
                        }

                        index = endifIndex + 11; // skip {% endif %}

                        if (isTrue) {
                            output += this.render(truthyTemplate, localContext);
                        } else {
                            output += this.render(falsyTemplate, localContext);
                        }
                    }
                }
            }
        }

        return output;
    }
}

// Global exposure for editor page
if (typeof window !== 'undefined') {
    window.LiquidSimulator = LiquidSimulator;
}
