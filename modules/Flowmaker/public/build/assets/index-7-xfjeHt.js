var Xm = e => {
    throw TypeError(e)
};
var ad = (e, t, n) => t.has(e) || Xm("Cannot " + n);
var W = (e, t, n) => (ad(e, t, "read from private field"), n ? n.call(e) : t.get(e)),
    ve = (e, t, n) => t.has(e) ? Xm("Cannot add the same private member more than once") : t instanceof WeakSet ? t.add(e) : t.set(e, n),
    ae = (e, t, n, r) => (ad(e, t, "write to private field"), r ? r.call(e, n) : t.set(e, n), n),
    qe = (e, t, n) => (ad(e, t, "access private method"), n);
var el = (e, t, n, r) => ({
    set _(o) {
        ae(e, t, o, n)
    }, get _() {
        return W(e, t, r)
    }
});

function v2(e, t) {
    for (var n = 0; n < t.length; n++) {
        const r = t[n];
        if (typeof r != "string" && !Array.isArray(r)) {
            for (const o in r) if (o !== "default" && !(o in e)) {
                const s = Object.getOwnPropertyDescriptor(r, o);
                s && Object.defineProperty(e, o, s.get ? s : {enumerable: !0, get: () => r[o]})
            }
        }
    }
    return Object.freeze(Object.defineProperty(e, Symbol.toStringTag, {value: "Module"}))
}

function zp(e) {
    return e && e.__esModule && Object.prototype.hasOwnProperty.call(e, "default") ? e.default : e
}

var dy = {exports: {}}, qc = {}, fy = {exports: {}}, pe = {};
/**
 * @license React
 * react.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var Ca = Symbol.for("react.element"), y2 = Symbol.for("react.portal"), x2 = Symbol.for("react.fragment"),
    w2 = Symbol.for("react.strict_mode"), b2 = Symbol.for("react.profiler"), C2 = Symbol.for("react.provider"),
    S2 = Symbol.for("react.context"), N2 = Symbol.for("react.forward_ref"), E2 = Symbol.for("react.suspense"),
    k2 = Symbol.for("react.memo"), _2 = Symbol.for("react.lazy"), qm = Symbol.iterator;

function j2(e) {
    return e === null || typeof e != "object" ? null : (e = qm && e[qm] || e["@@iterator"], typeof e == "function" ? e : null)
}

var py = {
    isMounted: function () {
        return !1
    }, enqueueForceUpdate: function () {
    }, enqueueReplaceState: function () {
    }, enqueueSetState: function () {
    }
}, hy = Object.assign, my = {};

function Gs(e, t, n) {
    this.props = e, this.context = t, this.refs = my, this.updater = n || py
}

Gs.prototype.isReactComponent = {};
Gs.prototype.setState = function (e, t) {
    if (typeof e != "object" && typeof e != "function" && e != null) throw Error("setState(...): takes an object of state variables to update or a function which returns an object of state variables.");
    this.updater.enqueueSetState(this, e, t, "setState")
};
Gs.prototype.forceUpdate = function (e) {
    this.updater.enqueueForceUpdate(this, e, "forceUpdate")
};

function gy() {
}

gy.prototype = Gs.prototype;

function Bp(e, t, n) {
    this.props = e, this.context = t, this.refs = my, this.updater = n || py
}

var Vp = Bp.prototype = new gy;
Vp.constructor = Bp;
hy(Vp, Gs.prototype);
Vp.isPureReactComponent = !0;
var Qm = Array.isArray, vy = Object.prototype.hasOwnProperty, Hp = {current: null},
    yy = {key: !0, ref: !0, __self: !0, __source: !0};

function xy(e, t, n) {
    var r, o = {}, s = null, i = null;
    if (t != null) for (r in t.ref !== void 0 && (i = t.ref), t.key !== void 0 && (s = "" + t.key), t) vy.call(t, r) && !yy.hasOwnProperty(r) && (o[r] = t[r]);
    var a = arguments.length - 2;
    if (a === 1) o.children = n; else if (1 < a) {
        for (var l = Array(a), u = 0; u < a; u++) l[u] = arguments[u + 2];
        o.children = l
    }
    if (e && e.defaultProps) for (r in a = e.defaultProps, a) o[r] === void 0 && (o[r] = a[r]);
    return {$$typeof: Ca, type: e, key: s, ref: i, props: o, _owner: Hp.current}
}

function P2(e, t) {
    return {$$typeof: Ca, type: e.type, key: t, ref: e.ref, props: e.props, _owner: e._owner}
}

function Up(e) {
    return typeof e == "object" && e !== null && e.$$typeof === Ca
}

function T2(e) {
    var t = {"=": "=0", ":": "=2"};
    return "$" + e.replace(/[=:]/g, function (n) {
        return t[n]
    })
}

var Zm = /\/+/g;

function ld(e, t) {
    return typeof e == "object" && e !== null && e.key != null ? T2("" + e.key) : t.toString(36)
}

function $l(e, t, n, r, o) {
    var s = typeof e;
    (s === "undefined" || s === "boolean") && (e = null);
    var i = !1;
    if (e === null) i = !0; else switch (s) {
        case"string":
        case"number":
            i = !0;
            break;
        case"object":
            switch (e.$$typeof) {
                case Ca:
                case y2:
                    i = !0
            }
    }
    if (i) return i = e, o = o(i), e = r === "" ? "." + ld(i, 0) : r, Qm(o) ? (n = "", e != null && (n = e.replace(Zm, "$&/") + "/"), $l(o, t, n, "", function (u) {
        return u
    })) : o != null && (Up(o) && (o = P2(o, n + (!o.key || i && i.key === o.key ? "" : ("" + o.key).replace(Zm, "$&/") + "/") + e)), t.push(o)), 1;
    if (i = 0, r = r === "" ? "." : r + ":", Qm(e)) for (var a = 0; a < e.length; a++) {
        s = e[a];
        var l = r + ld(s, a);
        i += $l(s, t, n, l, o)
    } else if (l = j2(e), typeof l == "function") for (e = l.call(e), a = 0; !(s = e.next()).done;) s = s.value, l = r + ld(s, a++), i += $l(s, t, n, l, o); else if (s === "object") throw t = String(e), Error("Objects are not valid as a React child (found: " + (t === "[object Object]" ? "object with keys {" + Object.keys(e).join(", ") + "}" : t) + "). If you meant to render a collection of children, use an array instead.");
    return i
}

function tl(e, t, n) {
    if (e == null) return e;
    var r = [], o = 0;
    return $l(e, r, "", "", function (s) {
        return t.call(n, s, o++)
    }), r
}

function M2(e) {
    if (e._status === -1) {
        var t = e._result;
        t = t(), t.then(function (n) {
            (e._status === 0 || e._status === -1) && (e._status = 1, e._result = n)
        }, function (n) {
            (e._status === 0 || e._status === -1) && (e._status = 2, e._result = n)
        }), e._status === -1 && (e._status = 0, e._result = t)
    }
    if (e._status === 1) return e._result.default;
    throw e._result
}

var ut = {current: null}, Ll = {transition: null},
    R2 = {ReactCurrentDispatcher: ut, ReactCurrentBatchConfig: Ll, ReactCurrentOwner: Hp};

function wy() {
    throw Error("act(...) is not supported in production builds of React.")
}

pe.Children = {
    map: tl, forEach: function (e, t, n) {
        tl(e, function () {
            t.apply(this, arguments)
        }, n)
    }, count: function (e) {
        var t = 0;
        return tl(e, function () {
            t++
        }), t
    }, toArray: function (e) {
        return tl(e, function (t) {
            return t
        }) || []
    }, only: function (e) {
        if (!Up(e)) throw Error("React.Children.only expected to receive a single React element child.");
        return e
    }
};
pe.Component = Gs;
pe.Fragment = x2;
pe.Profiler = b2;
pe.PureComponent = Bp;
pe.StrictMode = w2;
pe.Suspense = E2;
pe.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED = R2;
pe.act = wy;
pe.cloneElement = function (e, t, n) {
    if (e == null) throw Error("React.cloneElement(...): The argument must be a React element, but you passed " + e + ".");
    var r = hy({}, e.props), o = e.key, s = e.ref, i = e._owner;
    if (t != null) {
        if (t.ref !== void 0 && (s = t.ref, i = Hp.current), t.key !== void 0 && (o = "" + t.key), e.type && e.type.defaultProps) var a = e.type.defaultProps;
        for (l in t) vy.call(t, l) && !yy.hasOwnProperty(l) && (r[l] = t[l] === void 0 && a !== void 0 ? a[l] : t[l])
    }
    var l = arguments.length - 2;
    if (l === 1) r.children = n; else if (1 < l) {
        a = Array(l);
        for (var u = 0; u < l; u++) a[u] = arguments[u + 2];
        r.children = a
    }
    return {$$typeof: Ca, type: e.type, key: o, ref: s, props: r, _owner: i}
};
pe.createContext = function (e) {
    return e = {
        $$typeof: S2,
        _currentValue: e,
        _currentValue2: e,
        _threadCount: 0,
        Provider: null,
        Consumer: null,
        _defaultValue: null,
        _globalName: null
    }, e.Provider = {$$typeof: C2, _context: e}, e.Consumer = e
};
pe.createElement = xy;
pe.createFactory = function (e) {
    var t = xy.bind(null, e);
    return t.type = e, t
};
pe.createRef = function () {
    return {current: null}
};
pe.forwardRef = function (e) {
    return {$$typeof: N2, render: e}
};
pe.isValidElement = Up;
pe.lazy = function (e) {
    return {$$typeof: _2, _payload: {_status: -1, _result: e}, _init: M2}
};
pe.memo = function (e, t) {
    return {$$typeof: k2, type: e, compare: t === void 0 ? null : t}
};
pe.startTransition = function (e) {
    var t = Ll.transition;
    Ll.transition = {};
    try {
        e()
    } finally {
        Ll.transition = t
    }
};
pe.unstable_act = wy;
pe.useCallback = function (e, t) {
    return ut.current.useCallback(e, t)
};
pe.useContext = function (e) {
    return ut.current.useContext(e)
};
pe.useDebugValue = function () {
};
pe.useDeferredValue = function (e) {
    return ut.current.useDeferredValue(e)
};
pe.useEffect = function (e, t) {
    return ut.current.useEffect(e, t)
};
pe.useId = function () {
    return ut.current.useId()
};
pe.useImperativeHandle = function (e, t, n) {
    return ut.current.useImperativeHandle(e, t, n)
};
pe.useInsertionEffect = function (e, t) {
    return ut.current.useInsertionEffect(e, t)
};
pe.useLayoutEffect = function (e, t) {
    return ut.current.useLayoutEffect(e, t)
};
pe.useMemo = function (e, t) {
    return ut.current.useMemo(e, t)
};
pe.useReducer = function (e, t, n) {
    return ut.current.useReducer(e, t, n)
};
pe.useRef = function (e) {
    return ut.current.useRef(e)
};
pe.useState = function (e) {
    return ut.current.useState(e)
};
pe.useSyncExternalStore = function (e, t, n) {
    return ut.current.useSyncExternalStore(e, t, n)
};
pe.useTransition = function () {
    return ut.current.useTransition()
};
pe.version = "18.3.1";
fy.exports = pe;
var g = fy.exports;
const Z = zp(g), A2 = v2({__proto__: null, default: Z}, [g]);
/**
 * @license React
 * react-jsx-runtime.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var I2 = g, O2 = Symbol.for("react.element"), D2 = Symbol.for("react.fragment"),
    $2 = Object.prototype.hasOwnProperty, L2 = I2.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,
    F2 = {key: !0, ref: !0, __self: !0, __source: !0};

function by(e, t, n) {
    var r, o = {}, s = null, i = null;
    n !== void 0 && (s = "" + n), t.key !== void 0 && (s = "" + t.key), t.ref !== void 0 && (i = t.ref);
    for (r in t) $2.call(t, r) && !F2.hasOwnProperty(r) && (o[r] = t[r]);
    if (e && e.defaultProps) for (r in t = e.defaultProps, t) o[r] === void 0 && (o[r] = t[r]);
    return {$$typeof: O2, type: e, key: s, ref: i, props: o, _owner: L2.current}
}

qc.Fragment = D2;
qc.jsx = by;
qc.jsxs = by;
dy.exports = qc;
var c = dy.exports, Cy = {exports: {}}, It = {}, Sy = {exports: {}}, Ny = {};
/**
 * @license React
 * scheduler.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */(function (e) {
    function t(P, _) {
        var R = P.length;
        P.push(_);
        e:for (; 0 < R;) {
            var z = R - 1 >>> 1, I = P[z];
            if (0 < o(I, _)) P[z] = _, P[R] = I, R = z; else break e
        }
    }

    function n(P) {
        return P.length === 0 ? null : P[0]
    }

    function r(P) {
        if (P.length === 0) return null;
        var _ = P[0], R = P.pop();
        if (R !== _) {
            P[0] = R;
            e:for (var z = 0, I = P.length, V = I >>> 1; z < V;) {
                var H = 2 * (z + 1) - 1, U = P[H], K = H + 1, F = P[K];
                if (0 > o(U, R)) K < I && 0 > o(F, U) ? (P[z] = F, P[K] = R, z = K) : (P[z] = U, P[H] = R, z = H); else if (K < I && 0 > o(F, R)) P[z] = F, P[K] = R, z = K; else break e
            }
        }
        return _
    }

    function o(P, _) {
        var R = P.sortIndex - _.sortIndex;
        return R !== 0 ? R : P.id - _.id
    }

    if (typeof performance == "object" && typeof performance.now == "function") {
        var s = performance;
        e.unstable_now = function () {
            return s.now()
        }
    } else {
        var i = Date, a = i.now();
        e.unstable_now = function () {
            return i.now() - a
        }
    }
    var l = [], u = [], d = 1, f = null, p = 3, h = !1, x = !1, m = !1,
        b = typeof setTimeout == "function" ? setTimeout : null,
        y = typeof clearTimeout == "function" ? clearTimeout : null,
        w = typeof setImmediate < "u" ? setImmediate : null;
    typeof navigator < "u" && navigator.scheduling !== void 0 && navigator.scheduling.isInputPending !== void 0 && navigator.scheduling.isInputPending.bind(navigator.scheduling);

    function v(P) {
        for (var _ = n(u); _ !== null;) {
            if (_.callback === null) r(u); else if (_.startTime <= P) r(u), _.sortIndex = _.expirationTime, t(l, _); else break;
            _ = n(u)
        }
    }

    function C(P) {
        if (m = !1, v(P), !x) if (n(l) !== null) x = !0, j(S); else {
            var _ = n(u);
            _ !== null && A(C, _.startTime - P)
        }
    }

    function S(P, _) {
        x = !1, m && (m = !1, y(T), T = -1), h = !0;
        var R = p;
        try {
            for (v(_), f = n(l); f !== null && (!(f.expirationTime > _) || P && !L());) {
                var z = f.callback;
                if (typeof z == "function") {
                    f.callback = null, p = f.priorityLevel;
                    var I = z(f.expirationTime <= _);
                    _ = e.unstable_now(), typeof I == "function" ? f.callback = I : f === n(l) && r(l), v(_)
                } else r(l);
                f = n(l)
            }
            if (f !== null) var V = !0; else {
                var H = n(u);
                H !== null && A(C, H.startTime - _), V = !1
            }
            return V
        } finally {
            f = null, p = R, h = !1
        }
    }

    var N = !1, E = null, T = -1, O = 5, D = -1;

    function L() {
        return !(e.unstable_now() - D < O)
    }

    function $() {
        if (E !== null) {
            var P = e.unstable_now();
            D = P;
            var _ = !0;
            try {
                _ = E(!0, P)
            } finally {
                _ ? B() : (N = !1, E = null)
            }
        } else N = !1
    }

    var B;
    if (typeof w == "function") B = function () {
        w($)
    }; else if (typeof MessageChannel < "u") {
        var k = new MessageChannel, M = k.port2;
        k.port1.onmessage = $, B = function () {
            M.postMessage(null)
        }
    } else B = function () {
        b($, 0)
    };

    function j(P) {
        E = P, N || (N = !0, B())
    }

    function A(P, _) {
        T = b(function () {
            P(e.unstable_now())
        }, _)
    }

    e.unstable_IdlePriority = 5, e.unstable_ImmediatePriority = 1, e.unstable_LowPriority = 4, e.unstable_NormalPriority = 3, e.unstable_Profiling = null, e.unstable_UserBlockingPriority = 2, e.unstable_cancelCallback = function (P) {
        P.callback = null
    }, e.unstable_continueExecution = function () {
        x || h || (x = !0, j(S))
    }, e.unstable_forceFrameRate = function (P) {
        0 > P || 125 < P ? console.error("forceFrameRate takes a positive int between 0 and 125, forcing frame rates higher than 125 fps is not supported") : O = 0 < P ? Math.floor(1e3 / P) : 5
    }, e.unstable_getCurrentPriorityLevel = function () {
        return p
    }, e.unstable_getFirstCallbackNode = function () {
        return n(l)
    }, e.unstable_next = function (P) {
        switch (p) {
            case 1:
            case 2:
            case 3:
                var _ = 3;
                break;
            default:
                _ = p
        }
        var R = p;
        p = _;
        try {
            return P()
        } finally {
            p = R
        }
    }, e.unstable_pauseExecution = function () {
    }, e.unstable_requestPaint = function () {
    }, e.unstable_runWithPriority = function (P, _) {
        switch (P) {
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
                break;
            default:
                P = 3
        }
        var R = p;
        p = P;
        try {
            return _()
        } finally {
            p = R
        }
    }, e.unstable_scheduleCallback = function (P, _, R) {
        var z = e.unstable_now();
        switch (typeof R == "object" && R !== null ? (R = R.delay, R = typeof R == "number" && 0 < R ? z + R : z) : R = z, P) {
            case 1:
                var I = -1;
                break;
            case 2:
                I = 250;
                break;
            case 5:
                I = 1073741823;
                break;
            case 4:
                I = 1e4;
                break;
            default:
                I = 5e3
        }
        return I = R + I, P = {
            id: d++,
            callback: _,
            priorityLevel: P,
            startTime: R,
            expirationTime: I,
            sortIndex: -1
        }, R > z ? (P.sortIndex = R, t(u, P), n(l) === null && P === n(u) && (m ? (y(T), T = -1) : m = !0, A(C, R - z))) : (P.sortIndex = I, t(l, P), x || h || (x = !0, j(S))), P
    }, e.unstable_shouldYield = L, e.unstable_wrapCallback = function (P) {
        var _ = p;
        return function () {
            var R = p;
            p = _;
            try {
                return P.apply(this, arguments)
            } finally {
                p = R
            }
        }
    }
})(Ny);
Sy.exports = Ny;
var z2 = Sy.exports;
/**
 * @license React
 * react-dom.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var B2 = g, Rt = z2;

function G(e) {
    for (var t = "https://reactjs.org/docs/error-decoder.html?invariant=" + e, n = 1; n < arguments.length; n++) t += "&args[]=" + encodeURIComponent(arguments[n]);
    return "Minified React error #" + e + "; visit " + t + " for the full message or use the non-minified dev environment for full errors and additional helpful warnings."
}

var Ey = new Set, zi = {};

function $o(e, t) {
    Ms(e, t), Ms(e + "Capture", t)
}

function Ms(e, t) {
    for (zi[e] = t, e = 0; e < t.length; e++) Ey.add(t[e])
}

var Qn = !(typeof window > "u" || typeof window.document > "u" || typeof window.document.createElement > "u"),
    cf = Object.prototype.hasOwnProperty,
    V2 = /^[:A-Z_a-z\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u02FF\u0370-\u037D\u037F-\u1FFF\u200C-\u200D\u2070-\u218F\u2C00-\u2FEF\u3001-\uD7FF\uF900-\uFDCF\uFDF0-\uFFFD][:A-Z_a-z\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u02FF\u0370-\u037D\u037F-\u1FFF\u200C-\u200D\u2070-\u218F\u2C00-\u2FEF\u3001-\uD7FF\uF900-\uFDCF\uFDF0-\uFFFD\-.0-9\u00B7\u0300-\u036F\u203F-\u2040]*$/,
    Jm = {}, eg = {};

function H2(e) {
    return cf.call(eg, e) ? !0 : cf.call(Jm, e) ? !1 : V2.test(e) ? eg[e] = !0 : (Jm[e] = !0, !1)
}

function U2(e, t, n, r) {
    if (n !== null && n.type === 0) return !1;
    switch (typeof t) {
        case"function":
        case"symbol":
            return !0;
        case"boolean":
            return r ? !1 : n !== null ? !n.acceptsBooleans : (e = e.toLowerCase().slice(0, 5), e !== "data-" && e !== "aria-");
        default:
            return !1
    }
}

function W2(e, t, n, r) {
    if (t === null || typeof t > "u" || U2(e, t, n, r)) return !0;
    if (r) return !1;
    if (n !== null) switch (n.type) {
        case 3:
            return !t;
        case 4:
            return t === !1;
        case 5:
            return isNaN(t);
        case 6:
            return isNaN(t) || 1 > t
    }
    return !1
}

function dt(e, t, n, r, o, s, i) {
    this.acceptsBooleans = t === 2 || t === 3 || t === 4, this.attributeName = r, this.attributeNamespace = o, this.mustUseProperty = n, this.propertyName = e, this.type = t, this.sanitizeURL = s, this.removeEmptyString = i
}

var Ge = {};
"children dangerouslySetInnerHTML defaultValue defaultChecked innerHTML suppressContentEditableWarning suppressHydrationWarning style".split(" ").forEach(function (e) {
    Ge[e] = new dt(e, 0, !1, e, null, !1, !1)
});
[["acceptCharset", "accept-charset"], ["className", "class"], ["htmlFor", "for"], ["httpEquiv", "http-equiv"]].forEach(function (e) {
    var t = e[0];
    Ge[t] = new dt(t, 1, !1, e[1], null, !1, !1)
});
["contentEditable", "draggable", "spellCheck", "value"].forEach(function (e) {
    Ge[e] = new dt(e, 2, !1, e.toLowerCase(), null, !1, !1)
});
["autoReverse", "externalResourcesRequired", "focusable", "preserveAlpha"].forEach(function (e) {
    Ge[e] = new dt(e, 2, !1, e, null, !1, !1)
});
"allowFullScreen async autoFocus autoPlay controls default defer disabled disablePictureInPicture disableRemotePlayback formNoValidate hidden loop noModule noValidate open playsInline readOnly required reversed scoped seamless itemScope".split(" ").forEach(function (e) {
    Ge[e] = new dt(e, 3, !1, e.toLowerCase(), null, !1, !1)
});
["checked", "multiple", "muted", "selected"].forEach(function (e) {
    Ge[e] = new dt(e, 3, !0, e, null, !1, !1)
});
["capture", "download"].forEach(function (e) {
    Ge[e] = new dt(e, 4, !1, e, null, !1, !1)
});
["cols", "rows", "size", "span"].forEach(function (e) {
    Ge[e] = new dt(e, 6, !1, e, null, !1, !1)
});
["rowSpan", "start"].forEach(function (e) {
    Ge[e] = new dt(e, 5, !1, e.toLowerCase(), null, !1, !1)
});
var Wp = /[\-:]([a-z])/g;

function Kp(e) {
    return e[1].toUpperCase()
}

"accent-height alignment-baseline arabic-form baseline-shift cap-height clip-path clip-rule color-interpolation color-interpolation-filters color-profile color-rendering dominant-baseline enable-background fill-opacity fill-rule flood-color flood-opacity font-family font-size font-size-adjust font-stretch font-style font-variant font-weight glyph-name glyph-orientation-horizontal glyph-orientation-vertical horiz-adv-x horiz-origin-x image-rendering letter-spacing lighting-color marker-end marker-mid marker-start overline-position overline-thickness paint-order panose-1 pointer-events rendering-intent shape-rendering stop-color stop-opacity strikethrough-position strikethrough-thickness stroke-dasharray stroke-dashoffset stroke-linecap stroke-linejoin stroke-miterlimit stroke-opacity stroke-width text-anchor text-decoration text-rendering underline-position underline-thickness unicode-bidi unicode-range units-per-em v-alphabetic v-hanging v-ideographic v-mathematical vector-effect vert-adv-y vert-origin-x vert-origin-y word-spacing writing-mode xmlns:xlink x-height".split(" ").forEach(function (e) {
    var t = e.replace(Wp, Kp);
    Ge[t] = new dt(t, 1, !1, e, null, !1, !1)
});
"xlink:actuate xlink:arcrole xlink:role xlink:show xlink:title xlink:type".split(" ").forEach(function (e) {
    var t = e.replace(Wp, Kp);
    Ge[t] = new dt(t, 1, !1, e, "http://www.w3.org/1999/xlink", !1, !1)
});
["xml:base", "xml:lang", "xml:space"].forEach(function (e) {
    var t = e.replace(Wp, Kp);
    Ge[t] = new dt(t, 1, !1, e, "http://www.w3.org/XML/1998/namespace", !1, !1)
});
["tabIndex", "crossOrigin"].forEach(function (e) {
    Ge[e] = new dt(e, 1, !1, e.toLowerCase(), null, !1, !1)
});
Ge.xlinkHref = new dt("xlinkHref", 1, !1, "xlink:href", "http://www.w3.org/1999/xlink", !0, !1);
["src", "href", "action", "formAction"].forEach(function (e) {
    Ge[e] = new dt(e, 1, !1, e.toLowerCase(), null, !0, !0)
});

function Yp(e, t, n, r) {
    var o = Ge.hasOwnProperty(t) ? Ge[t] : null;
    (o !== null ? o.type !== 0 : r || !(2 < t.length) || t[0] !== "o" && t[0] !== "O" || t[1] !== "n" && t[1] !== "N") && (W2(t, n, o, r) && (n = null), r || o === null ? H2(t) && (n === null ? e.removeAttribute(t) : e.setAttribute(t, "" + n)) : o.mustUseProperty ? e[o.propertyName] = n === null ? o.type === 3 ? !1 : "" : n : (t = o.attributeName, r = o.attributeNamespace, n === null ? e.removeAttribute(t) : (o = o.type, n = o === 3 || o === 4 && n === !0 ? "" : "" + n, r ? e.setAttributeNS(r, t, n) : e.setAttribute(t, n))))
}

var or = B2.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED, nl = Symbol.for("react.element"),
    Qo = Symbol.for("react.portal"), Zo = Symbol.for("react.fragment"), Gp = Symbol.for("react.strict_mode"),
    uf = Symbol.for("react.profiler"), ky = Symbol.for("react.provider"), _y = Symbol.for("react.context"),
    Xp = Symbol.for("react.forward_ref"), df = Symbol.for("react.suspense"), ff = Symbol.for("react.suspense_list"),
    qp = Symbol.for("react.memo"), br = Symbol.for("react.lazy"), jy = Symbol.for("react.offscreen"),
    tg = Symbol.iterator;

function ai(e) {
    return e === null || typeof e != "object" ? null : (e = tg && e[tg] || e["@@iterator"], typeof e == "function" ? e : null)
}

var Ae = Object.assign, cd;

function wi(e) {
    if (cd === void 0) try {
        throw Error()
    } catch (n) {
        var t = n.stack.trim().match(/\n( *(at )?)/);
        cd = t && t[1] || ""
    }
    return `
` + cd + e
}

var ud = !1;

function dd(e, t) {
    if (!e || ud) return "";
    ud = !0;
    var n = Error.prepareStackTrace;
    Error.prepareStackTrace = void 0;
    try {
        if (t) if (t = function () {
            throw Error()
        }, Object.defineProperty(t.prototype, "props", {
            set: function () {
                throw Error()
            }
        }), typeof Reflect == "object" && Reflect.construct) {
            try {
                Reflect.construct(t, [])
            } catch (u) {
                var r = u
            }
            Reflect.construct(e, [], t)
        } else {
            try {
                t.call()
            } catch (u) {
                r = u
            }
            e.call(t.prototype)
        } else {
            try {
                throw Error()
            } catch (u) {
                r = u
            }
            e()
        }
    } catch (u) {
        if (u && r && typeof u.stack == "string") {
            for (var o = u.stack.split(`
`), s = r.stack.split(`
`), i = o.length - 1, a = s.length - 1; 1 <= i && 0 <= a && o[i] !== s[a];) a--;
            for (; 1 <= i && 0 <= a; i--, a--) if (o[i] !== s[a]) {
                if (i !== 1 || a !== 1) do if (i--, a--, 0 > a || o[i] !== s[a]) {
                    var l = `
` + o[i].replace(" at new ", " at ");
                    return e.displayName && l.includes("<anonymous>") && (l = l.replace("<anonymous>", e.displayName)), l
                } while (1 <= i && 0 <= a);
                break
            }
        }
    } finally {
        ud = !1, Error.prepareStackTrace = n
    }
    return (e = e ? e.displayName || e.name : "") ? wi(e) : ""
}

function K2(e) {
    switch (e.tag) {
        case 5:
            return wi(e.type);
        case 16:
            return wi("Lazy");
        case 13:
            return wi("Suspense");
        case 19:
            return wi("SuspenseList");
        case 0:
        case 2:
        case 15:
            return e = dd(e.type, !1), e;
        case 11:
            return e = dd(e.type.render, !1), e;
        case 1:
            return e = dd(e.type, !0), e;
        default:
            return ""
    }
}

function pf(e) {
    if (e == null) return null;
    if (typeof e == "function") return e.displayName || e.name || null;
    if (typeof e == "string") return e;
    switch (e) {
        case Zo:
            return "Fragment";
        case Qo:
            return "Portal";
        case uf:
            return "Profiler";
        case Gp:
            return "StrictMode";
        case df:
            return "Suspense";
        case ff:
            return "SuspenseList"
    }
    if (typeof e == "object") switch (e.$$typeof) {
        case _y:
            return (e.displayName || "Context") + ".Consumer";
        case ky:
            return (e._context.displayName || "Context") + ".Provider";
        case Xp:
            var t = e.render;
            return e = e.displayName, e || (e = t.displayName || t.name || "", e = e !== "" ? "ForwardRef(" + e + ")" : "ForwardRef"), e;
        case qp:
            return t = e.displayName || null, t !== null ? t : pf(e.type) || "Memo";
        case br:
            t = e._payload, e = e._init;
            try {
                return pf(e(t))
            } catch {
            }
    }
    return null
}

function Y2(e) {
    var t = e.type;
    switch (e.tag) {
        case 24:
            return "Cache";
        case 9:
            return (t.displayName || "Context") + ".Consumer";
        case 10:
            return (t._context.displayName || "Context") + ".Provider";
        case 18:
            return "DehydratedFragment";
        case 11:
            return e = t.render, e = e.displayName || e.name || "", t.displayName || (e !== "" ? "ForwardRef(" + e + ")" : "ForwardRef");
        case 7:
            return "Fragment";
        case 5:
            return t;
        case 4:
            return "Portal";
        case 3:
            return "Root";
        case 6:
            return "Text";
        case 16:
            return pf(t);
        case 8:
            return t === Gp ? "StrictMode" : "Mode";
        case 22:
            return "Offscreen";
        case 12:
            return "Profiler";
        case 21:
            return "Scope";
        case 13:
            return "Suspense";
        case 19:
            return "SuspenseList";
        case 25:
            return "TracingMarker";
        case 1:
        case 0:
        case 17:
        case 2:
        case 14:
        case 15:
            if (typeof t == "function") return t.displayName || t.name || null;
            if (typeof t == "string") return t
    }
    return null
}

function Hr(e) {
    switch (typeof e) {
        case"boolean":
        case"number":
        case"string":
        case"undefined":
            return e;
        case"object":
            return e;
        default:
            return ""
    }
}

function Py(e) {
    var t = e.type;
    return (e = e.nodeName) && e.toLowerCase() === "input" && (t === "checkbox" || t === "radio")
}

function G2(e) {
    var t = Py(e) ? "checked" : "value", n = Object.getOwnPropertyDescriptor(e.constructor.prototype, t), r = "" + e[t];
    if (!e.hasOwnProperty(t) && typeof n < "u" && typeof n.get == "function" && typeof n.set == "function") {
        var o = n.get, s = n.set;
        return Object.defineProperty(e, t, {
            configurable: !0, get: function () {
                return o.call(this)
            }, set: function (i) {
                r = "" + i, s.call(this, i)
            }
        }), Object.defineProperty(e, t, {enumerable: n.enumerable}), {
            getValue: function () {
                return r
            }, setValue: function (i) {
                r = "" + i
            }, stopTracking: function () {
                e._valueTracker = null, delete e[t]
            }
        }
    }
}

function rl(e) {
    e._valueTracker || (e._valueTracker = G2(e))
}

function Ty(e) {
    if (!e) return !1;
    var t = e._valueTracker;
    if (!t) return !0;
    var n = t.getValue(), r = "";
    return e && (r = Py(e) ? e.checked ? "true" : "false" : e.value), e = r, e !== n ? (t.setValue(e), !0) : !1
}

function sc(e) {
    if (e = e || (typeof document < "u" ? document : void 0), typeof e > "u") return null;
    try {
        return e.activeElement || e.body
    } catch {
        return e.body
    }
}

function hf(e, t) {
    var n = t.checked;
    return Ae({}, t, {
        defaultChecked: void 0,
        defaultValue: void 0,
        value: void 0,
        checked: n ?? e._wrapperState.initialChecked
    })
}

function ng(e, t) {
    var n = t.defaultValue == null ? "" : t.defaultValue, r = t.checked != null ? t.checked : t.defaultChecked;
    n = Hr(t.value != null ? t.value : n), e._wrapperState = {
        initialChecked: r,
        initialValue: n,
        controlled: t.type === "checkbox" || t.type === "radio" ? t.checked != null : t.value != null
    }
}

function My(e, t) {
    t = t.checked, t != null && Yp(e, "checked", t, !1)
}

function mf(e, t) {
    My(e, t);
    var n = Hr(t.value), r = t.type;
    if (n != null) r === "number" ? (n === 0 && e.value === "" || e.value != n) && (e.value = "" + n) : e.value !== "" + n && (e.value = "" + n); else if (r === "submit" || r === "reset") {
        e.removeAttribute("value");
        return
    }
    t.hasOwnProperty("value") ? gf(e, t.type, n) : t.hasOwnProperty("defaultValue") && gf(e, t.type, Hr(t.defaultValue)), t.checked == null && t.defaultChecked != null && (e.defaultChecked = !!t.defaultChecked)
}

function rg(e, t, n) {
    if (t.hasOwnProperty("value") || t.hasOwnProperty("defaultValue")) {
        var r = t.type;
        if (!(r !== "submit" && r !== "reset" || t.value !== void 0 && t.value !== null)) return;
        t = "" + e._wrapperState.initialValue, n || t === e.value || (e.value = t), e.defaultValue = t
    }
    n = e.name, n !== "" && (e.name = ""), e.defaultChecked = !!e._wrapperState.initialChecked, n !== "" && (e.name = n)
}

function gf(e, t, n) {
    (t !== "number" || sc(e.ownerDocument) !== e) && (n == null ? e.defaultValue = "" + e._wrapperState.initialValue : e.defaultValue !== "" + n && (e.defaultValue = "" + n))
}

var bi = Array.isArray;

function ds(e, t, n, r) {
    if (e = e.options, t) {
        t = {};
        for (var o = 0; o < n.length; o++) t["$" + n[o]] = !0;
        for (n = 0; n < e.length; n++) o = t.hasOwnProperty("$" + e[n].value), e[n].selected !== o && (e[n].selected = o), o && r && (e[n].defaultSelected = !0)
    } else {
        for (n = "" + Hr(n), t = null, o = 0; o < e.length; o++) {
            if (e[o].value === n) {
                e[o].selected = !0, r && (e[o].defaultSelected = !0);
                return
            }
            t !== null || e[o].disabled || (t = e[o])
        }
        t !== null && (t.selected = !0)
    }
}

function vf(e, t) {
    if (t.dangerouslySetInnerHTML != null) throw Error(G(91));
    return Ae({}, t, {value: void 0, defaultValue: void 0, children: "" + e._wrapperState.initialValue})
}

function og(e, t) {
    var n = t.value;
    if (n == null) {
        if (n = t.children, t = t.defaultValue, n != null) {
            if (t != null) throw Error(G(92));
            if (bi(n)) {
                if (1 < n.length) throw Error(G(93));
                n = n[0]
            }
            t = n
        }
        t == null && (t = ""), n = t
    }
    e._wrapperState = {initialValue: Hr(n)}
}

function Ry(e, t) {
    var n = Hr(t.value), r = Hr(t.defaultValue);
    n != null && (n = "" + n, n !== e.value && (e.value = n), t.defaultValue == null && e.defaultValue !== n && (e.defaultValue = n)), r != null && (e.defaultValue = "" + r)
}

function sg(e) {
    var t = e.textContent;
    t === e._wrapperState.initialValue && t !== "" && t !== null && (e.value = t)
}

function Ay(e) {
    switch (e) {
        case"svg":
            return "http://www.w3.org/2000/svg";
        case"math":
            return "http://www.w3.org/1998/Math/MathML";
        default:
            return "http://www.w3.org/1999/xhtml"
    }
}

function yf(e, t) {
    return e == null || e === "http://www.w3.org/1999/xhtml" ? Ay(t) : e === "http://www.w3.org/2000/svg" && t === "foreignObject" ? "http://www.w3.org/1999/xhtml" : e
}

var ol, Iy = function (e) {
    return typeof MSApp < "u" && MSApp.execUnsafeLocalFunction ? function (t, n, r, o) {
        MSApp.execUnsafeLocalFunction(function () {
            return e(t, n, r, o)
        })
    } : e
}(function (e, t) {
    if (e.namespaceURI !== "http://www.w3.org/2000/svg" || "innerHTML" in e) e.innerHTML = t; else {
        for (ol = ol || document.createElement("div"), ol.innerHTML = "<svg>" + t.valueOf().toString() + "</svg>", t = ol.firstChild; e.firstChild;) e.removeChild(e.firstChild);
        for (; t.firstChild;) e.appendChild(t.firstChild)
    }
});

function Bi(e, t) {
    if (t) {
        var n = e.firstChild;
        if (n && n === e.lastChild && n.nodeType === 3) {
            n.nodeValue = t;
            return
        }
    }
    e.textContent = t
}

var ji = {
    animationIterationCount: !0,
    aspectRatio: !0,
    borderImageOutset: !0,
    borderImageSlice: !0,
    borderImageWidth: !0,
    boxFlex: !0,
    boxFlexGroup: !0,
    boxOrdinalGroup: !0,
    columnCount: !0,
    columns: !0,
    flex: !0,
    flexGrow: !0,
    flexPositive: !0,
    flexShrink: !0,
    flexNegative: !0,
    flexOrder: !0,
    gridArea: !0,
    gridRow: !0,
    gridRowEnd: !0,
    gridRowSpan: !0,
    gridRowStart: !0,
    gridColumn: !0,
    gridColumnEnd: !0,
    gridColumnSpan: !0,
    gridColumnStart: !0,
    fontWeight: !0,
    lineClamp: !0,
    lineHeight: !0,
    opacity: !0,
    order: !0,
    orphans: !0,
    tabSize: !0,
    widows: !0,
    zIndex: !0,
    zoom: !0,
    fillOpacity: !0,
    floodOpacity: !0,
    stopOpacity: !0,
    strokeDasharray: !0,
    strokeDashoffset: !0,
    strokeMiterlimit: !0,
    strokeOpacity: !0,
    strokeWidth: !0
}, X2 = ["Webkit", "ms", "Moz", "O"];
Object.keys(ji).forEach(function (e) {
    X2.forEach(function (t) {
        t = t + e.charAt(0).toUpperCase() + e.substring(1), ji[t] = ji[e]
    })
});

function Oy(e, t, n) {
    return t == null || typeof t == "boolean" || t === "" ? "" : n || typeof t != "number" || t === 0 || ji.hasOwnProperty(e) && ji[e] ? ("" + t).trim() : t + "px"
}

function Dy(e, t) {
    e = e.style;
    for (var n in t) if (t.hasOwnProperty(n)) {
        var r = n.indexOf("--") === 0, o = Oy(n, t[n], r);
        n === "float" && (n = "cssFloat"), r ? e.setProperty(n, o) : e[n] = o
    }
}

var q2 = Ae({menuitem: !0}, {
    area: !0,
    base: !0,
    br: !0,
    col: !0,
    embed: !0,
    hr: !0,
    img: !0,
    input: !0,
    keygen: !0,
    link: !0,
    meta: !0,
    param: !0,
    source: !0,
    track: !0,
    wbr: !0
});

function xf(e, t) {
    if (t) {
        if (q2[e] && (t.children != null || t.dangerouslySetInnerHTML != null)) throw Error(G(137, e));
        if (t.dangerouslySetInnerHTML != null) {
            if (t.children != null) throw Error(G(60));
            if (typeof t.dangerouslySetInnerHTML != "object" || !("__html" in t.dangerouslySetInnerHTML)) throw Error(G(61))
        }
        if (t.style != null && typeof t.style != "object") throw Error(G(62))
    }
}

function wf(e, t) {
    if (e.indexOf("-") === -1) return typeof t.is == "string";
    switch (e) {
        case"annotation-xml":
        case"color-profile":
        case"font-face":
        case"font-face-src":
        case"font-face-uri":
        case"font-face-format":
        case"font-face-name":
        case"missing-glyph":
            return !1;
        default:
            return !0
    }
}

var bf = null;

function Qp(e) {
    return e = e.target || e.srcElement || window, e.correspondingUseElement && (e = e.correspondingUseElement), e.nodeType === 3 ? e.parentNode : e
}

var Cf = null, fs = null, ps = null;

function ig(e) {
    if (e = Ea(e)) {
        if (typeof Cf != "function") throw Error(G(280));
        var t = e.stateNode;
        t && (t = tu(t), Cf(e.stateNode, e.type, t))
    }
}

function $y(e) {
    fs ? ps ? ps.push(e) : ps = [e] : fs = e
}

function Ly() {
    if (fs) {
        var e = fs, t = ps;
        if (ps = fs = null, ig(e), t) for (e = 0; e < t.length; e++) ig(t[e])
    }
}

function Fy(e, t) {
    return e(t)
}

function zy() {
}

var fd = !1;

function By(e, t, n) {
    if (fd) return e(t, n);
    fd = !0;
    try {
        return Fy(e, t, n)
    } finally {
        fd = !1, (fs !== null || ps !== null) && (zy(), Ly())
    }
}

function Vi(e, t) {
    var n = e.stateNode;
    if (n === null) return null;
    var r = tu(n);
    if (r === null) return null;
    n = r[t];
    e:switch (t) {
        case"onClick":
        case"onClickCapture":
        case"onDoubleClick":
        case"onDoubleClickCapture":
        case"onMouseDown":
        case"onMouseDownCapture":
        case"onMouseMove":
        case"onMouseMoveCapture":
        case"onMouseUp":
        case"onMouseUpCapture":
        case"onMouseEnter":
            (r = !r.disabled) || (e = e.type, r = !(e === "button" || e === "input" || e === "select" || e === "textarea")), e = !r;
            break e;
        default:
            e = !1
    }
    if (e) return null;
    if (n && typeof n != "function") throw Error(G(231, t, typeof n));
    return n
}

var Sf = !1;
if (Qn) try {
    var li = {};
    Object.defineProperty(li, "passive", {
        get: function () {
            Sf = !0
        }
    }), window.addEventListener("test", li, li), window.removeEventListener("test", li, li)
} catch {
    Sf = !1
}

function Q2(e, t, n, r, o, s, i, a, l) {
    var u = Array.prototype.slice.call(arguments, 3);
    try {
        t.apply(n, u)
    } catch (d) {
        this.onError(d)
    }
}

var Pi = !1, ic = null, ac = !1, Nf = null, Z2 = {
    onError: function (e) {
        Pi = !0, ic = e
    }
};

function J2(e, t, n, r, o, s, i, a, l) {
    Pi = !1, ic = null, Q2.apply(Z2, arguments)
}

function ek(e, t, n, r, o, s, i, a, l) {
    if (J2.apply(this, arguments), Pi) {
        if (Pi) {
            var u = ic;
            Pi = !1, ic = null
        } else throw Error(G(198));
        ac || (ac = !0, Nf = u)
    }
}

function Lo(e) {
    var t = e, n = e;
    if (e.alternate) for (; t.return;) t = t.return; else {
        e = t;
        do t = e, t.flags & 4098 && (n = t.return), e = t.return; while (e)
    }
    return t.tag === 3 ? n : null
}

function Vy(e) {
    if (e.tag === 13) {
        var t = e.memoizedState;
        if (t === null && (e = e.alternate, e !== null && (t = e.memoizedState)), t !== null) return t.dehydrated
    }
    return null
}

function ag(e) {
    if (Lo(e) !== e) throw Error(G(188))
}

function tk(e) {
    var t = e.alternate;
    if (!t) {
        if (t = Lo(e), t === null) throw Error(G(188));
        return t !== e ? null : e
    }
    for (var n = e, r = t; ;) {
        var o = n.return;
        if (o === null) break;
        var s = o.alternate;
        if (s === null) {
            if (r = o.return, r !== null) {
                n = r;
                continue
            }
            break
        }
        if (o.child === s.child) {
            for (s = o.child; s;) {
                if (s === n) return ag(o), e;
                if (s === r) return ag(o), t;
                s = s.sibling
            }
            throw Error(G(188))
        }
        if (n.return !== r.return) n = o, r = s; else {
            for (var i = !1, a = o.child; a;) {
                if (a === n) {
                    i = !0, n = o, r = s;
                    break
                }
                if (a === r) {
                    i = !0, r = o, n = s;
                    break
                }
                a = a.sibling
            }
            if (!i) {
                for (a = s.child; a;) {
                    if (a === n) {
                        i = !0, n = s, r = o;
                        break
                    }
                    if (a === r) {
                        i = !0, r = s, n = o;
                        break
                    }
                    a = a.sibling
                }
                if (!i) throw Error(G(189))
            }
        }
        if (n.alternate !== r) throw Error(G(190))
    }
    if (n.tag !== 3) throw Error(G(188));
    return n.stateNode.current === n ? e : t
}

function Hy(e) {
    return e = tk(e), e !== null ? Uy(e) : null
}

function Uy(e) {
    if (e.tag === 5 || e.tag === 6) return e;
    for (e = e.child; e !== null;) {
        var t = Uy(e);
        if (t !== null) return t;
        e = e.sibling
    }
    return null
}

var Wy = Rt.unstable_scheduleCallback, lg = Rt.unstable_cancelCallback, nk = Rt.unstable_shouldYield,
    rk = Rt.unstable_requestPaint, Le = Rt.unstable_now, ok = Rt.unstable_getCurrentPriorityLevel,
    Zp = Rt.unstable_ImmediatePriority, Ky = Rt.unstable_UserBlockingPriority, lc = Rt.unstable_NormalPriority,
    sk = Rt.unstable_LowPriority, Yy = Rt.unstable_IdlePriority, Qc = null, Pn = null;

function ik(e) {
    if (Pn && typeof Pn.onCommitFiberRoot == "function") try {
        Pn.onCommitFiberRoot(Qc, e, void 0, (e.current.flags & 128) === 128)
    } catch {
    }
}

var ln = Math.clz32 ? Math.clz32 : ck, ak = Math.log, lk = Math.LN2;

function ck(e) {
    return e >>>= 0, e === 0 ? 32 : 31 - (ak(e) / lk | 0) | 0
}

var sl = 64, il = 4194304;

function Ci(e) {
    switch (e & -e) {
        case 1:
            return 1;
        case 2:
            return 2;
        case 4:
            return 4;
        case 8:
            return 8;
        case 16:
            return 16;
        case 32:
            return 32;
        case 64:
        case 128:
        case 256:
        case 512:
        case 1024:
        case 2048:
        case 4096:
        case 8192:
        case 16384:
        case 32768:
        case 65536:
        case 131072:
        case 262144:
        case 524288:
        case 1048576:
        case 2097152:
            return e & 4194240;
        case 4194304:
        case 8388608:
        case 16777216:
        case 33554432:
        case 67108864:
            return e & 130023424;
        case 134217728:
            return 134217728;
        case 268435456:
            return 268435456;
        case 536870912:
            return 536870912;
        case 1073741824:
            return 1073741824;
        default:
            return e
    }
}

function cc(e, t) {
    var n = e.pendingLanes;
    if (n === 0) return 0;
    var r = 0, o = e.suspendedLanes, s = e.pingedLanes, i = n & 268435455;
    if (i !== 0) {
        var a = i & ~o;
        a !== 0 ? r = Ci(a) : (s &= i, s !== 0 && (r = Ci(s)))
    } else i = n & ~o, i !== 0 ? r = Ci(i) : s !== 0 && (r = Ci(s));
    if (r === 0) return 0;
    if (t !== 0 && t !== r && !(t & o) && (o = r & -r, s = t & -t, o >= s || o === 16 && (s & 4194240) !== 0)) return t;
    if (r & 4 && (r |= n & 16), t = e.entangledLanes, t !== 0) for (e = e.entanglements, t &= r; 0 < t;) n = 31 - ln(t), o = 1 << n, r |= e[n], t &= ~o;
    return r
}

function uk(e, t) {
    switch (e) {
        case 1:
        case 2:
        case 4:
            return t + 250;
        case 8:
        case 16:
        case 32:
        case 64:
        case 128:
        case 256:
        case 512:
        case 1024:
        case 2048:
        case 4096:
        case 8192:
        case 16384:
        case 32768:
        case 65536:
        case 131072:
        case 262144:
        case 524288:
        case 1048576:
        case 2097152:
            return t + 5e3;
        case 4194304:
        case 8388608:
        case 16777216:
        case 33554432:
        case 67108864:
            return -1;
        case 134217728:
        case 268435456:
        case 536870912:
        case 1073741824:
            return -1;
        default:
            return -1
    }
}

function dk(e, t) {
    for (var n = e.suspendedLanes, r = e.pingedLanes, o = e.expirationTimes, s = e.pendingLanes; 0 < s;) {
        var i = 31 - ln(s), a = 1 << i, l = o[i];
        l === -1 ? (!(a & n) || a & r) && (o[i] = uk(a, t)) : l <= t && (e.expiredLanes |= a), s &= ~a
    }
}

function Ef(e) {
    return e = e.pendingLanes & -1073741825, e !== 0 ? e : e & 1073741824 ? 1073741824 : 0
}

function Gy() {
    var e = sl;
    return sl <<= 1, !(sl & 4194240) && (sl = 64), e
}

function pd(e) {
    for (var t = [], n = 0; 31 > n; n++) t.push(e);
    return t
}

function Sa(e, t, n) {
    e.pendingLanes |= t, t !== 536870912 && (e.suspendedLanes = 0, e.pingedLanes = 0), e = e.eventTimes, t = 31 - ln(t), e[t] = n
}

function fk(e, t) {
    var n = e.pendingLanes & ~t;
    e.pendingLanes = t, e.suspendedLanes = 0, e.pingedLanes = 0, e.expiredLanes &= t, e.mutableReadLanes &= t, e.entangledLanes &= t, t = e.entanglements;
    var r = e.eventTimes;
    for (e = e.expirationTimes; 0 < n;) {
        var o = 31 - ln(n), s = 1 << o;
        t[o] = 0, r[o] = -1, e[o] = -1, n &= ~s
    }
}

function Jp(e, t) {
    var n = e.entangledLanes |= t;
    for (e = e.entanglements; n;) {
        var r = 31 - ln(n), o = 1 << r;
        o & t | e[r] & t && (e[r] |= t), n &= ~o
    }
}

var xe = 0;

function Xy(e) {
    return e &= -e, 1 < e ? 4 < e ? e & 268435455 ? 16 : 536870912 : 4 : 1
}

var qy, eh, Qy, Zy, Jy, kf = !1, al = [], Ir = null, Or = null, Dr = null, Hi = new Map, Ui = new Map, Nr = [],
    pk = "mousedown mouseup touchcancel touchend touchstart auxclick dblclick pointercancel pointerdown pointerup dragend dragstart drop compositionend compositionstart keydown keypress keyup input textInput copy cut paste click change contextmenu reset submit".split(" ");

function cg(e, t) {
    switch (e) {
        case"focusin":
        case"focusout":
            Ir = null;
            break;
        case"dragenter":
        case"dragleave":
            Or = null;
            break;
        case"mouseover":
        case"mouseout":
            Dr = null;
            break;
        case"pointerover":
        case"pointerout":
            Hi.delete(t.pointerId);
            break;
        case"gotpointercapture":
        case"lostpointercapture":
            Ui.delete(t.pointerId)
    }
}

function ci(e, t, n, r, o, s) {
    return e === null || e.nativeEvent !== s ? (e = {
        blockedOn: t,
        domEventName: n,
        eventSystemFlags: r,
        nativeEvent: s,
        targetContainers: [o]
    }, t !== null && (t = Ea(t), t !== null && eh(t)), e) : (e.eventSystemFlags |= r, t = e.targetContainers, o !== null && t.indexOf(o) === -1 && t.push(o), e)
}

function hk(e, t, n, r, o) {
    switch (t) {
        case"focusin":
            return Ir = ci(Ir, e, t, n, r, o), !0;
        case"dragenter":
            return Or = ci(Or, e, t, n, r, o), !0;
        case"mouseover":
            return Dr = ci(Dr, e, t, n, r, o), !0;
        case"pointerover":
            var s = o.pointerId;
            return Hi.set(s, ci(Hi.get(s) || null, e, t, n, r, o)), !0;
        case"gotpointercapture":
            return s = o.pointerId, Ui.set(s, ci(Ui.get(s) || null, e, t, n, r, o)), !0
    }
    return !1
}

function ex(e) {
    var t = co(e.target);
    if (t !== null) {
        var n = Lo(t);
        if (n !== null) {
            if (t = n.tag, t === 13) {
                if (t = Vy(n), t !== null) {
                    e.blockedOn = t, Jy(e.priority, function () {
                        Qy(n)
                    });
                    return
                }
            } else if (t === 3 && n.stateNode.current.memoizedState.isDehydrated) {
                e.blockedOn = n.tag === 3 ? n.stateNode.containerInfo : null;
                return
            }
        }
    }
    e.blockedOn = null
}

function Fl(e) {
    if (e.blockedOn !== null) return !1;
    for (var t = e.targetContainers; 0 < t.length;) {
        var n = _f(e.domEventName, e.eventSystemFlags, t[0], e.nativeEvent);
        if (n === null) {
            n = e.nativeEvent;
            var r = new n.constructor(n.type, n);
            bf = r, n.target.dispatchEvent(r), bf = null
        } else return t = Ea(n), t !== null && eh(t), e.blockedOn = n, !1;
        t.shift()
    }
    return !0
}

function ug(e, t, n) {
    Fl(e) && n.delete(t)
}

function mk() {
    kf = !1, Ir !== null && Fl(Ir) && (Ir = null), Or !== null && Fl(Or) && (Or = null), Dr !== null && Fl(Dr) && (Dr = null), Hi.forEach(ug), Ui.forEach(ug)
}

function ui(e, t) {
    e.blockedOn === t && (e.blockedOn = null, kf || (kf = !0, Rt.unstable_scheduleCallback(Rt.unstable_NormalPriority, mk)))
}

function Wi(e) {
    function t(o) {
        return ui(o, e)
    }

    if (0 < al.length) {
        ui(al[0], e);
        for (var n = 1; n < al.length; n++) {
            var r = al[n];
            r.blockedOn === e && (r.blockedOn = null)
        }
    }
    for (Ir !== null && ui(Ir, e), Or !== null && ui(Or, e), Dr !== null && ui(Dr, e), Hi.forEach(t), Ui.forEach(t), n = 0; n < Nr.length; n++) r = Nr[n], r.blockedOn === e && (r.blockedOn = null);
    for (; 0 < Nr.length && (n = Nr[0], n.blockedOn === null);) ex(n), n.blockedOn === null && Nr.shift()
}

var hs = or.ReactCurrentBatchConfig, uc = !0;

function gk(e, t, n, r) {
    var o = xe, s = hs.transition;
    hs.transition = null;
    try {
        xe = 1, th(e, t, n, r)
    } finally {
        xe = o, hs.transition = s
    }
}

function vk(e, t, n, r) {
    var o = xe, s = hs.transition;
    hs.transition = null;
    try {
        xe = 4, th(e, t, n, r)
    } finally {
        xe = o, hs.transition = s
    }
}

function th(e, t, n, r) {
    if (uc) {
        var o = _f(e, t, n, r);
        if (o === null) Sd(e, t, r, dc, n), cg(e, r); else if (hk(o, e, t, n, r)) r.stopPropagation(); else if (cg(e, r), t & 4 && -1 < pk.indexOf(e)) {
            for (; o !== null;) {
                var s = Ea(o);
                if (s !== null && qy(s), s = _f(e, t, n, r), s === null && Sd(e, t, r, dc, n), s === o) break;
                o = s
            }
            o !== null && r.stopPropagation()
        } else Sd(e, t, r, null, n)
    }
}

var dc = null;

function _f(e, t, n, r) {
    if (dc = null, e = Qp(r), e = co(e), e !== null) if (t = Lo(e), t === null) e = null; else if (n = t.tag, n === 13) {
        if (e = Vy(t), e !== null) return e;
        e = null
    } else if (n === 3) {
        if (t.stateNode.current.memoizedState.isDehydrated) return t.tag === 3 ? t.stateNode.containerInfo : null;
        e = null
    } else t !== e && (e = null);
    return dc = e, null
}

function tx(e) {
    switch (e) {
        case"cancel":
        case"click":
        case"close":
        case"contextmenu":
        case"copy":
        case"cut":
        case"auxclick":
        case"dblclick":
        case"dragend":
        case"dragstart":
        case"drop":
        case"focusin":
        case"focusout":
        case"input":
        case"invalid":
        case"keydown":
        case"keypress":
        case"keyup":
        case"mousedown":
        case"mouseup":
        case"paste":
        case"pause":
        case"play":
        case"pointercancel":
        case"pointerdown":
        case"pointerup":
        case"ratechange":
        case"reset":
        case"resize":
        case"seeked":
        case"submit":
        case"touchcancel":
        case"touchend":
        case"touchstart":
        case"volumechange":
        case"change":
        case"selectionchange":
        case"textInput":
        case"compositionstart":
        case"compositionend":
        case"compositionupdate":
        case"beforeblur":
        case"afterblur":
        case"beforeinput":
        case"blur":
        case"fullscreenchange":
        case"focus":
        case"hashchange":
        case"popstate":
        case"select":
        case"selectstart":
            return 1;
        case"drag":
        case"dragenter":
        case"dragexit":
        case"dragleave":
        case"dragover":
        case"mousemove":
        case"mouseout":
        case"mouseover":
        case"pointermove":
        case"pointerout":
        case"pointerover":
        case"scroll":
        case"toggle":
        case"touchmove":
        case"wheel":
        case"mouseenter":
        case"mouseleave":
        case"pointerenter":
        case"pointerleave":
            return 4;
        case"message":
            switch (ok()) {
                case Zp:
                    return 1;
                case Ky:
                    return 4;
                case lc:
                case sk:
                    return 16;
                case Yy:
                    return 536870912;
                default:
                    return 16
            }
        default:
            return 16
    }
}

var Rr = null, nh = null, zl = null;

function nx() {
    if (zl) return zl;
    var e, t = nh, n = t.length, r, o = "value" in Rr ? Rr.value : Rr.textContent, s = o.length;
    for (e = 0; e < n && t[e] === o[e]; e++) ;
    var i = n - e;
    for (r = 1; r <= i && t[n - r] === o[s - r]; r++) ;
    return zl = o.slice(e, 1 < r ? 1 - r : void 0)
}

function Bl(e) {
    var t = e.keyCode;
    return "charCode" in e ? (e = e.charCode, e === 0 && t === 13 && (e = 13)) : e = t, e === 10 && (e = 13), 32 <= e || e === 13 ? e : 0
}

function ll() {
    return !0
}

function dg() {
    return !1
}

function Ot(e) {
    function t(n, r, o, s, i) {
        this._reactName = n, this._targetInst = o, this.type = r, this.nativeEvent = s, this.target = i, this.currentTarget = null;
        for (var a in e) e.hasOwnProperty(a) && (n = e[a], this[a] = n ? n(s) : s[a]);
        return this.isDefaultPrevented = (s.defaultPrevented != null ? s.defaultPrevented : s.returnValue === !1) ? ll : dg, this.isPropagationStopped = dg, this
    }

    return Ae(t.prototype, {
        preventDefault: function () {
            this.defaultPrevented = !0;
            var n = this.nativeEvent;
            n && (n.preventDefault ? n.preventDefault() : typeof n.returnValue != "unknown" && (n.returnValue = !1), this.isDefaultPrevented = ll)
        }, stopPropagation: function () {
            var n = this.nativeEvent;
            n && (n.stopPropagation ? n.stopPropagation() : typeof n.cancelBubble != "unknown" && (n.cancelBubble = !0), this.isPropagationStopped = ll)
        }, persist: function () {
        }, isPersistent: ll
    }), t
}

var Xs = {
        eventPhase: 0, bubbles: 0, cancelable: 0, timeStamp: function (e) {
            return e.timeStamp || Date.now()
        }, defaultPrevented: 0, isTrusted: 0
    }, rh = Ot(Xs), Na = Ae({}, Xs, {view: 0, detail: 0}), yk = Ot(Na), hd, md, di, Zc = Ae({}, Na, {
        screenX: 0,
        screenY: 0,
        clientX: 0,
        clientY: 0,
        pageX: 0,
        pageY: 0,
        ctrlKey: 0,
        shiftKey: 0,
        altKey: 0,
        metaKey: 0,
        getModifierState: oh,
        button: 0,
        buttons: 0,
        relatedTarget: function (e) {
            return e.relatedTarget === void 0 ? e.fromElement === e.srcElement ? e.toElement : e.fromElement : e.relatedTarget
        },
        movementX: function (e) {
            return "movementX" in e ? e.movementX : (e !== di && (di && e.type === "mousemove" ? (hd = e.screenX - di.screenX, md = e.screenY - di.screenY) : md = hd = 0, di = e), hd)
        },
        movementY: function (e) {
            return "movementY" in e ? e.movementY : md
        }
    }), fg = Ot(Zc), xk = Ae({}, Zc, {dataTransfer: 0}), wk = Ot(xk), bk = Ae({}, Na, {relatedTarget: 0}), gd = Ot(bk),
    Ck = Ae({}, Xs, {animationName: 0, elapsedTime: 0, pseudoElement: 0}), Sk = Ot(Ck), Nk = Ae({}, Xs, {
        clipboardData: function (e) {
            return "clipboardData" in e ? e.clipboardData : window.clipboardData
        }
    }), Ek = Ot(Nk), kk = Ae({}, Xs, {data: 0}), pg = Ot(kk), _k = {
        Esc: "Escape",
        Spacebar: " ",
        Left: "ArrowLeft",
        Up: "ArrowUp",
        Right: "ArrowRight",
        Down: "ArrowDown",
        Del: "Delete",
        Win: "OS",
        Menu: "ContextMenu",
        Apps: "ContextMenu",
        Scroll: "ScrollLock",
        MozPrintableKey: "Unidentified"
    }, jk = {
        8: "Backspace",
        9: "Tab",
        12: "Clear",
        13: "Enter",
        16: "Shift",
        17: "Control",
        18: "Alt",
        19: "Pause",
        20: "CapsLock",
        27: "Escape",
        32: " ",
        33: "PageUp",
        34: "PageDown",
        35: "End",
        36: "Home",
        37: "ArrowLeft",
        38: "ArrowUp",
        39: "ArrowRight",
        40: "ArrowDown",
        45: "Insert",
        46: "Delete",
        112: "F1",
        113: "F2",
        114: "F3",
        115: "F4",
        116: "F5",
        117: "F6",
        118: "F7",
        119: "F8",
        120: "F9",
        121: "F10",
        122: "F11",
        123: "F12",
        144: "NumLock",
        145: "ScrollLock",
        224: "Meta"
    }, Pk = {Alt: "altKey", Control: "ctrlKey", Meta: "metaKey", Shift: "shiftKey"};

function Tk(e) {
    var t = this.nativeEvent;
    return t.getModifierState ? t.getModifierState(e) : (e = Pk[e]) ? !!t[e] : !1
}

function oh() {
    return Tk
}

var Mk = Ae({}, Na, {
    key: function (e) {
        if (e.key) {
            var t = _k[e.key] || e.key;
            if (t !== "Unidentified") return t
        }
        return e.type === "keypress" ? (e = Bl(e), e === 13 ? "Enter" : String.fromCharCode(e)) : e.type === "keydown" || e.type === "keyup" ? jk[e.keyCode] || "Unidentified" : ""
    },
    code: 0,
    location: 0,
    ctrlKey: 0,
    shiftKey: 0,
    altKey: 0,
    metaKey: 0,
    repeat: 0,
    locale: 0,
    getModifierState: oh,
    charCode: function (e) {
        return e.type === "keypress" ? Bl(e) : 0
    },
    keyCode: function (e) {
        return e.type === "keydown" || e.type === "keyup" ? e.keyCode : 0
    },
    which: function (e) {
        return e.type === "keypress" ? Bl(e) : e.type === "keydown" || e.type === "keyup" ? e.keyCode : 0
    }
}), Rk = Ot(Mk), Ak = Ae({}, Zc, {
    pointerId: 0,
    width: 0,
    height: 0,
    pressure: 0,
    tangentialPressure: 0,
    tiltX: 0,
    tiltY: 0,
    twist: 0,
    pointerType: 0,
    isPrimary: 0
}), hg = Ot(Ak), Ik = Ae({}, Na, {
    touches: 0,
    targetTouches: 0,
    changedTouches: 0,
    altKey: 0,
    metaKey: 0,
    ctrlKey: 0,
    shiftKey: 0,
    getModifierState: oh
}), Ok = Ot(Ik), Dk = Ae({}, Xs, {propertyName: 0, elapsedTime: 0, pseudoElement: 0}), $k = Ot(Dk), Lk = Ae({}, Zc, {
    deltaX: function (e) {
        return "deltaX" in e ? e.deltaX : "wheelDeltaX" in e ? -e.wheelDeltaX : 0
    }, deltaY: function (e) {
        return "deltaY" in e ? e.deltaY : "wheelDeltaY" in e ? -e.wheelDeltaY : "wheelDelta" in e ? -e.wheelDelta : 0
    }, deltaZ: 0, deltaMode: 0
}), Fk = Ot(Lk), zk = [9, 13, 27, 32], sh = Qn && "CompositionEvent" in window, Ti = null;
Qn && "documentMode" in document && (Ti = document.documentMode);
var Bk = Qn && "TextEvent" in window && !Ti, rx = Qn && (!sh || Ti && 8 < Ti && 11 >= Ti), mg = " ", gg = !1;

function ox(e, t) {
    switch (e) {
        case"keyup":
            return zk.indexOf(t.keyCode) !== -1;
        case"keydown":
            return t.keyCode !== 229;
        case"keypress":
        case"mousedown":
        case"focusout":
            return !0;
        default:
            return !1
    }
}

function sx(e) {
    return e = e.detail, typeof e == "object" && "data" in e ? e.data : null
}

var Jo = !1;

function Vk(e, t) {
    switch (e) {
        case"compositionend":
            return sx(t);
        case"keypress":
            return t.which !== 32 ? null : (gg = !0, mg);
        case"textInput":
            return e = t.data, e === mg && gg ? null : e;
        default:
            return null
    }
}

function Hk(e, t) {
    if (Jo) return e === "compositionend" || !sh && ox(e, t) ? (e = nx(), zl = nh = Rr = null, Jo = !1, e) : null;
    switch (e) {
        case"paste":
            return null;
        case"keypress":
            if (!(t.ctrlKey || t.altKey || t.metaKey) || t.ctrlKey && t.altKey) {
                if (t.char && 1 < t.char.length) return t.char;
                if (t.which) return String.fromCharCode(t.which)
            }
            return null;
        case"compositionend":
            return rx && t.locale !== "ko" ? null : t.data;
        default:
            return null
    }
}

var Uk = {
    color: !0,
    date: !0,
    datetime: !0,
    "datetime-local": !0,
    email: !0,
    month: !0,
    number: !0,
    password: !0,
    range: !0,
    search: !0,
    tel: !0,
    text: !0,
    time: !0,
    url: !0,
    week: !0
};

function vg(e) {
    var t = e && e.nodeName && e.nodeName.toLowerCase();
    return t === "input" ? !!Uk[e.type] : t === "textarea"
}

function ix(e, t, n, r) {
    $y(r), t = fc(t, "onChange"), 0 < t.length && (n = new rh("onChange", "change", null, n, r), e.push({
        event: n,
        listeners: t
    }))
}

var Mi = null, Ki = null;

function Wk(e) {
    vx(e, 0)
}

function Jc(e) {
    var t = ns(e);
    if (Ty(t)) return e
}

function Kk(e, t) {
    if (e === "change") return t
}

var ax = !1;
if (Qn) {
    var vd;
    if (Qn) {
        var yd = "oninput" in document;
        if (!yd) {
            var yg = document.createElement("div");
            yg.setAttribute("oninput", "return;"), yd = typeof yg.oninput == "function"
        }
        vd = yd
    } else vd = !1;
    ax = vd && (!document.documentMode || 9 < document.documentMode)
}

function xg() {
    Mi && (Mi.detachEvent("onpropertychange", lx), Ki = Mi = null)
}

function lx(e) {
    if (e.propertyName === "value" && Jc(Ki)) {
        var t = [];
        ix(t, Ki, e, Qp(e)), By(Wk, t)
    }
}

function Yk(e, t, n) {
    e === "focusin" ? (xg(), Mi = t, Ki = n, Mi.attachEvent("onpropertychange", lx)) : e === "focusout" && xg()
}

function Gk(e) {
    if (e === "selectionchange" || e === "keyup" || e === "keydown") return Jc(Ki)
}

function Xk(e, t) {
    if (e === "click") return Jc(t)
}

function qk(e, t) {
    if (e === "input" || e === "change") return Jc(t)
}

function Qk(e, t) {
    return e === t && (e !== 0 || 1 / e === 1 / t) || e !== e && t !== t
}

var fn = typeof Object.is == "function" ? Object.is : Qk;

function Yi(e, t) {
    if (fn(e, t)) return !0;
    if (typeof e != "object" || e === null || typeof t != "object" || t === null) return !1;
    var n = Object.keys(e), r = Object.keys(t);
    if (n.length !== r.length) return !1;
    for (r = 0; r < n.length; r++) {
        var o = n[r];
        if (!cf.call(t, o) || !fn(e[o], t[o])) return !1
    }
    return !0
}

function wg(e) {
    for (; e && e.firstChild;) e = e.firstChild;
    return e
}

function bg(e, t) {
    var n = wg(e);
    e = 0;
    for (var r; n;) {
        if (n.nodeType === 3) {
            if (r = e + n.textContent.length, e <= t && r >= t) return {node: n, offset: t - e};
            e = r
        }
        e:{
            for (; n;) {
                if (n.nextSibling) {
                    n = n.nextSibling;
                    break e
                }
                n = n.parentNode
            }
            n = void 0
        }
        n = wg(n)
    }
}

function cx(e, t) {
    return e && t ? e === t ? !0 : e && e.nodeType === 3 ? !1 : t && t.nodeType === 3 ? cx(e, t.parentNode) : "contains" in e ? e.contains(t) : e.compareDocumentPosition ? !!(e.compareDocumentPosition(t) & 16) : !1 : !1
}

function ux() {
    for (var e = window, t = sc(); t instanceof e.HTMLIFrameElement;) {
        try {
            var n = typeof t.contentWindow.location.href == "string"
        } catch {
            n = !1
        }
        if (n) e = t.contentWindow; else break;
        t = sc(e.document)
    }
    return t
}

function ih(e) {
    var t = e && e.nodeName && e.nodeName.toLowerCase();
    return t && (t === "input" && (e.type === "text" || e.type === "search" || e.type === "tel" || e.type === "url" || e.type === "password") || t === "textarea" || e.contentEditable === "true")
}

function Zk(e) {
    var t = ux(), n = e.focusedElem, r = e.selectionRange;
    if (t !== n && n && n.ownerDocument && cx(n.ownerDocument.documentElement, n)) {
        if (r !== null && ih(n)) {
            if (t = r.start, e = r.end, e === void 0 && (e = t), "selectionStart" in n) n.selectionStart = t, n.selectionEnd = Math.min(e, n.value.length); else if (e = (t = n.ownerDocument || document) && t.defaultView || window, e.getSelection) {
                e = e.getSelection();
                var o = n.textContent.length, s = Math.min(r.start, o);
                r = r.end === void 0 ? s : Math.min(r.end, o), !e.extend && s > r && (o = r, r = s, s = o), o = bg(n, s);
                var i = bg(n, r);
                o && i && (e.rangeCount !== 1 || e.anchorNode !== o.node || e.anchorOffset !== o.offset || e.focusNode !== i.node || e.focusOffset !== i.offset) && (t = t.createRange(), t.setStart(o.node, o.offset), e.removeAllRanges(), s > r ? (e.addRange(t), e.extend(i.node, i.offset)) : (t.setEnd(i.node, i.offset), e.addRange(t)))
            }
        }
        for (t = [], e = n; e = e.parentNode;) e.nodeType === 1 && t.push({
            element: e,
            left: e.scrollLeft,
            top: e.scrollTop
        });
        for (typeof n.focus == "function" && n.focus(), n = 0; n < t.length; n++) e = t[n], e.element.scrollLeft = e.left, e.element.scrollTop = e.top
    }
}

var Jk = Qn && "documentMode" in document && 11 >= document.documentMode, es = null, jf = null, Ri = null, Pf = !1;

function Cg(e, t, n) {
    var r = n.window === n ? n.document : n.nodeType === 9 ? n : n.ownerDocument;
    Pf || es == null || es !== sc(r) || (r = es, "selectionStart" in r && ih(r) ? r = {
        start: r.selectionStart,
        end: r.selectionEnd
    } : (r = (r.ownerDocument && r.ownerDocument.defaultView || window).getSelection(), r = {
        anchorNode: r.anchorNode,
        anchorOffset: r.anchorOffset,
        focusNode: r.focusNode,
        focusOffset: r.focusOffset
    }), Ri && Yi(Ri, r) || (Ri = r, r = fc(jf, "onSelect"), 0 < r.length && (t = new rh("onSelect", "select", null, t, n), e.push({
        event: t,
        listeners: r
    }), t.target = es)))
}

function cl(e, t) {
    var n = {};
    return n[e.toLowerCase()] = t.toLowerCase(), n["Webkit" + e] = "webkit" + t, n["Moz" + e] = "moz" + t, n
}

var ts = {
    animationend: cl("Animation", "AnimationEnd"),
    animationiteration: cl("Animation", "AnimationIteration"),
    animationstart: cl("Animation", "AnimationStart"),
    transitionend: cl("Transition", "TransitionEnd")
}, xd = {}, dx = {};
Qn && (dx = document.createElement("div").style, "AnimationEvent" in window || (delete ts.animationend.animation, delete ts.animationiteration.animation, delete ts.animationstart.animation), "TransitionEvent" in window || delete ts.transitionend.transition);

function eu(e) {
    if (xd[e]) return xd[e];
    if (!ts[e]) return e;
    var t = ts[e], n;
    for (n in t) if (t.hasOwnProperty(n) && n in dx) return xd[e] = t[n];
    return e
}

var fx = eu("animationend"), px = eu("animationiteration"), hx = eu("animationstart"), mx = eu("transitionend"),
    gx = new Map,
    Sg = "abort auxClick cancel canPlay canPlayThrough click close contextMenu copy cut drag dragEnd dragEnter dragExit dragLeave dragOver dragStart drop durationChange emptied encrypted ended error gotPointerCapture input invalid keyDown keyPress keyUp load loadedData loadedMetadata loadStart lostPointerCapture mouseDown mouseMove mouseOut mouseOver mouseUp paste pause play playing pointerCancel pointerDown pointerMove pointerOut pointerOver pointerUp progress rateChange reset resize seeked seeking stalled submit suspend timeUpdate touchCancel touchEnd touchStart volumeChange scroll toggle touchMove waiting wheel".split(" ");

function Qr(e, t) {
    gx.set(e, t), $o(t, [e])
}

for (var wd = 0; wd < Sg.length; wd++) {
    var bd = Sg[wd], e_ = bd.toLowerCase(), t_ = bd[0].toUpperCase() + bd.slice(1);
    Qr(e_, "on" + t_)
}
Qr(fx, "onAnimationEnd");
Qr(px, "onAnimationIteration");
Qr(hx, "onAnimationStart");
Qr("dblclick", "onDoubleClick");
Qr("focusin", "onFocus");
Qr("focusout", "onBlur");
Qr(mx, "onTransitionEnd");
Ms("onMouseEnter", ["mouseout", "mouseover"]);
Ms("onMouseLeave", ["mouseout", "mouseover"]);
Ms("onPointerEnter", ["pointerout", "pointerover"]);
Ms("onPointerLeave", ["pointerout", "pointerover"]);
$o("onChange", "change click focusin focusout input keydown keyup selectionchange".split(" "));
$o("onSelect", "focusout contextmenu dragend focusin keydown keyup mousedown mouseup selectionchange".split(" "));
$o("onBeforeInput", ["compositionend", "keypress", "textInput", "paste"]);
$o("onCompositionEnd", "compositionend focusout keydown keypress keyup mousedown".split(" "));
$o("onCompositionStart", "compositionstart focusout keydown keypress keyup mousedown".split(" "));
$o("onCompositionUpdate", "compositionupdate focusout keydown keypress keyup mousedown".split(" "));
var Si = "abort canplay canplaythrough durationchange emptied encrypted ended error loadeddata loadedmetadata loadstart pause play playing progress ratechange resize seeked seeking stalled suspend timeupdate volumechange waiting".split(" "),
    n_ = new Set("cancel close invalid load scroll toggle".split(" ").concat(Si));

function Ng(e, t, n) {
    var r = e.type || "unknown-event";
    e.currentTarget = n, ek(r, t, void 0, e), e.currentTarget = null
}

function vx(e, t) {
    t = (t & 4) !== 0;
    for (var n = 0; n < e.length; n++) {
        var r = e[n], o = r.event;
        r = r.listeners;
        e:{
            var s = void 0;
            if (t) for (var i = r.length - 1; 0 <= i; i--) {
                var a = r[i], l = a.instance, u = a.currentTarget;
                if (a = a.listener, l !== s && o.isPropagationStopped()) break e;
                Ng(o, a, u), s = l
            } else for (i = 0; i < r.length; i++) {
                if (a = r[i], l = a.instance, u = a.currentTarget, a = a.listener, l !== s && o.isPropagationStopped()) break e;
                Ng(o, a, u), s = l
            }
        }
    }
    if (ac) throw e = Nf, ac = !1, Nf = null, e
}

function ke(e, t) {
    var n = t[If];
    n === void 0 && (n = t[If] = new Set);
    var r = e + "__bubble";
    n.has(r) || (yx(t, e, 2, !1), n.add(r))
}

function Cd(e, t, n) {
    var r = 0;
    t && (r |= 4), yx(n, e, r, t)
}

var ul = "_reactListening" + Math.random().toString(36).slice(2);

function Gi(e) {
    if (!e[ul]) {
        e[ul] = !0, Ey.forEach(function (n) {
            n !== "selectionchange" && (n_.has(n) || Cd(n, !1, e), Cd(n, !0, e))
        });
        var t = e.nodeType === 9 ? e : e.ownerDocument;
        t === null || t[ul] || (t[ul] = !0, Cd("selectionchange", !1, t))
    }
}

function yx(e, t, n, r) {
    switch (tx(t)) {
        case 1:
            var o = gk;
            break;
        case 4:
            o = vk;
            break;
        default:
            o = th
    }
    n = o.bind(null, t, n, e), o = void 0, !Sf || t !== "touchstart" && t !== "touchmove" && t !== "wheel" || (o = !0), r ? o !== void 0 ? e.addEventListener(t, n, {
        capture: !0,
        passive: o
    }) : e.addEventListener(t, n, !0) : o !== void 0 ? e.addEventListener(t, n, {passive: o}) : e.addEventListener(t, n, !1)
}

function Sd(e, t, n, r, o) {
    var s = r;
    if (!(t & 1) && !(t & 2) && r !== null) e:for (; ;) {
        if (r === null) return;
        var i = r.tag;
        if (i === 3 || i === 4) {
            var a = r.stateNode.containerInfo;
            if (a === o || a.nodeType === 8 && a.parentNode === o) break;
            if (i === 4) for (i = r.return; i !== null;) {
                var l = i.tag;
                if ((l === 3 || l === 4) && (l = i.stateNode.containerInfo, l === o || l.nodeType === 8 && l.parentNode === o)) return;
                i = i.return
            }
            for (; a !== null;) {
                if (i = co(a), i === null) return;
                if (l = i.tag, l === 5 || l === 6) {
                    r = s = i;
                    continue e
                }
                a = a.parentNode
            }
        }
        r = r.return
    }
    By(function () {
        var u = s, d = Qp(n), f = [];
        e:{
            var p = gx.get(e);
            if (p !== void 0) {
                var h = rh, x = e;
                switch (e) {
                    case"keypress":
                        if (Bl(n) === 0) break e;
                    case"keydown":
                    case"keyup":
                        h = Rk;
                        break;
                    case"focusin":
                        x = "focus", h = gd;
                        break;
                    case"focusout":
                        x = "blur", h = gd;
                        break;
                    case"beforeblur":
                    case"afterblur":
                        h = gd;
                        break;
                    case"click":
                        if (n.button === 2) break e;
                    case"auxclick":
                    case"dblclick":
                    case"mousedown":
                    case"mousemove":
                    case"mouseup":
                    case"mouseout":
                    case"mouseover":
                    case"contextmenu":
                        h = fg;
                        break;
                    case"drag":
                    case"dragend":
                    case"dragenter":
                    case"dragexit":
                    case"dragleave":
                    case"dragover":
                    case"dragstart":
                    case"drop":
                        h = wk;
                        break;
                    case"touchcancel":
                    case"touchend":
                    case"touchmove":
                    case"touchstart":
                        h = Ok;
                        break;
                    case fx:
                    case px:
                    case hx:
                        h = Sk;
                        break;
                    case mx:
                        h = $k;
                        break;
                    case"scroll":
                        h = yk;
                        break;
                    case"wheel":
                        h = Fk;
                        break;
                    case"copy":
                    case"cut":
                    case"paste":
                        h = Ek;
                        break;
                    case"gotpointercapture":
                    case"lostpointercapture":
                    case"pointercancel":
                    case"pointerdown":
                    case"pointermove":
                    case"pointerout":
                    case"pointerover":
                    case"pointerup":
                        h = hg
                }
                var m = (t & 4) !== 0, b = !m && e === "scroll", y = m ? p !== null ? p + "Capture" : null : p;
                m = [];
                for (var w = u, v; w !== null;) {
                    v = w;
                    var C = v.stateNode;
                    if (v.tag === 5 && C !== null && (v = C, y !== null && (C = Vi(w, y), C != null && m.push(Xi(w, C, v)))), b) break;
                    w = w.return
                }
                0 < m.length && (p = new h(p, x, null, n, d), f.push({event: p, listeners: m}))
            }
        }
        if (!(t & 7)) {
            e:{
                if (p = e === "mouseover" || e === "pointerover", h = e === "mouseout" || e === "pointerout", p && n !== bf && (x = n.relatedTarget || n.fromElement) && (co(x) || x[Zn])) break e;
                if ((h || p) && (p = d.window === d ? d : (p = d.ownerDocument) ? p.defaultView || p.parentWindow : window, h ? (x = n.relatedTarget || n.toElement, h = u, x = x ? co(x) : null, x !== null && (b = Lo(x), x !== b || x.tag !== 5 && x.tag !== 6) && (x = null)) : (h = null, x = u), h !== x)) {
                    if (m = fg, C = "onMouseLeave", y = "onMouseEnter", w = "mouse", (e === "pointerout" || e === "pointerover") && (m = hg, C = "onPointerLeave", y = "onPointerEnter", w = "pointer"), b = h == null ? p : ns(h), v = x == null ? p : ns(x), p = new m(C, w + "leave", h, n, d), p.target = b, p.relatedTarget = v, C = null, co(d) === u && (m = new m(y, w + "enter", x, n, d), m.target = v, m.relatedTarget = b, C = m), b = C, h && x) t:{
                        for (m = h, y = x, w = 0, v = m; v; v = Uo(v)) w++;
                        for (v = 0, C = y; C; C = Uo(C)) v++;
                        for (; 0 < w - v;) m = Uo(m), w--;
                        for (; 0 < v - w;) y = Uo(y), v--;
                        for (; w--;) {
                            if (m === y || y !== null && m === y.alternate) break t;
                            m = Uo(m), y = Uo(y)
                        }
                        m = null
                    } else m = null;
                    h !== null && Eg(f, p, h, m, !1), x !== null && b !== null && Eg(f, b, x, m, !0)
                }
            }
            e:{
                if (p = u ? ns(u) : window, h = p.nodeName && p.nodeName.toLowerCase(), h === "select" || h === "input" && p.type === "file") var S = Kk; else if (vg(p)) if (ax) S = qk; else {
                    S = Gk;
                    var N = Yk
                } else (h = p.nodeName) && h.toLowerCase() === "input" && (p.type === "checkbox" || p.type === "radio") && (S = Xk);
                if (S && (S = S(e, u))) {
                    ix(f, S, n, d);
                    break e
                }
                N && N(e, p, u), e === "focusout" && (N = p._wrapperState) && N.controlled && p.type === "number" && gf(p, "number", p.value)
            }
            switch (N = u ? ns(u) : window, e) {
                case"focusin":
                    (vg(N) || N.contentEditable === "true") && (es = N, jf = u, Ri = null);
                    break;
                case"focusout":
                    Ri = jf = es = null;
                    break;
                case"mousedown":
                    Pf = !0;
                    break;
                case"contextmenu":
                case"mouseup":
                case"dragend":
                    Pf = !1, Cg(f, n, d);
                    break;
                case"selectionchange":
                    if (Jk) break;
                case"keydown":
                case"keyup":
                    Cg(f, n, d)
            }
            var E;
            if (sh) e:{
                switch (e) {
                    case"compositionstart":
                        var T = "onCompositionStart";
                        break e;
                    case"compositionend":
                        T = "onCompositionEnd";
                        break e;
                    case"compositionupdate":
                        T = "onCompositionUpdate";
                        break e
                }
                T = void 0
            } else Jo ? ox(e, n) && (T = "onCompositionEnd") : e === "keydown" && n.keyCode === 229 && (T = "onCompositionStart");
            T && (rx && n.locale !== "ko" && (Jo || T !== "onCompositionStart" ? T === "onCompositionEnd" && Jo && (E = nx()) : (Rr = d, nh = "value" in Rr ? Rr.value : Rr.textContent, Jo = !0)), N = fc(u, T), 0 < N.length && (T = new pg(T, e, null, n, d), f.push({
                event: T,
                listeners: N
            }), E ? T.data = E : (E = sx(n), E !== null && (T.data = E)))), (E = Bk ? Vk(e, n) : Hk(e, n)) && (u = fc(u, "onBeforeInput"), 0 < u.length && (d = new pg("onBeforeInput", "beforeinput", null, n, d), f.push({
                event: d,
                listeners: u
            }), d.data = E))
        }
        vx(f, t)
    })
}

function Xi(e, t, n) {
    return {instance: e, listener: t, currentTarget: n}
}

function fc(e, t) {
    for (var n = t + "Capture", r = []; e !== null;) {
        var o = e, s = o.stateNode;
        o.tag === 5 && s !== null && (o = s, s = Vi(e, n), s != null && r.unshift(Xi(e, s, o)), s = Vi(e, t), s != null && r.push(Xi(e, s, o))), e = e.return
    }
    return r
}

function Uo(e) {
    if (e === null) return null;
    do e = e.return; while (e && e.tag !== 5);
    return e || null
}

function Eg(e, t, n, r, o) {
    for (var s = t._reactName, i = []; n !== null && n !== r;) {
        var a = n, l = a.alternate, u = a.stateNode;
        if (l !== null && l === r) break;
        a.tag === 5 && u !== null && (a = u, o ? (l = Vi(n, s), l != null && i.unshift(Xi(n, l, a))) : o || (l = Vi(n, s), l != null && i.push(Xi(n, l, a)))), n = n.return
    }
    i.length !== 0 && e.push({event: t, listeners: i})
}

var r_ = /\r\n?/g, o_ = /\u0000|\uFFFD/g;

function kg(e) {
    return (typeof e == "string" ? e : "" + e).replace(r_, `
`).replace(o_, "")
}

function dl(e, t, n) {
    if (t = kg(t), kg(e) !== t && n) throw Error(G(425))
}

function pc() {
}

var Tf = null, Mf = null;

function Rf(e, t) {
    return e === "textarea" || e === "noscript" || typeof t.children == "string" || typeof t.children == "number" || typeof t.dangerouslySetInnerHTML == "object" && t.dangerouslySetInnerHTML !== null && t.dangerouslySetInnerHTML.__html != null
}

var Af = typeof setTimeout == "function" ? setTimeout : void 0,
    s_ = typeof clearTimeout == "function" ? clearTimeout : void 0,
    _g = typeof Promise == "function" ? Promise : void 0,
    i_ = typeof queueMicrotask == "function" ? queueMicrotask : typeof _g < "u" ? function (e) {
        return _g.resolve(null).then(e).catch(a_)
    } : Af;

function a_(e) {
    setTimeout(function () {
        throw e
    })
}

function Nd(e, t) {
    var n = t, r = 0;
    do {
        var o = n.nextSibling;
        if (e.removeChild(n), o && o.nodeType === 8) if (n = o.data, n === "/$") {
            if (r === 0) {
                e.removeChild(o), Wi(t);
                return
            }
            r--
        } else n !== "$" && n !== "$?" && n !== "$!" || r++;
        n = o
    } while (n);
    Wi(t)
}

function $r(e) {
    for (; e != null; e = e.nextSibling) {
        var t = e.nodeType;
        if (t === 1 || t === 3) break;
        if (t === 8) {
            if (t = e.data, t === "$" || t === "$!" || t === "$?") break;
            if (t === "/$") return null
        }
    }
    return e
}

function jg(e) {
    e = e.previousSibling;
    for (var t = 0; e;) {
        if (e.nodeType === 8) {
            var n = e.data;
            if (n === "$" || n === "$!" || n === "$?") {
                if (t === 0) return e;
                t--
            } else n === "/$" && t++
        }
        e = e.previousSibling
    }
    return null
}

var qs = Math.random().toString(36).slice(2), _n = "__reactFiber$" + qs, qi = "__reactProps$" + qs,
    Zn = "__reactContainer$" + qs, If = "__reactEvents$" + qs, l_ = "__reactListeners$" + qs,
    c_ = "__reactHandles$" + qs;

function co(e) {
    var t = e[_n];
    if (t) return t;
    for (var n = e.parentNode; n;) {
        if (t = n[Zn] || n[_n]) {
            if (n = t.alternate, t.child !== null || n !== null && n.child !== null) for (e = jg(e); e !== null;) {
                if (n = e[_n]) return n;
                e = jg(e)
            }
            return t
        }
        e = n, n = e.parentNode
    }
    return null
}

function Ea(e) {
    return e = e[_n] || e[Zn], !e || e.tag !== 5 && e.tag !== 6 && e.tag !== 13 && e.tag !== 3 ? null : e
}

function ns(e) {
    if (e.tag === 5 || e.tag === 6) return e.stateNode;
    throw Error(G(33))
}

function tu(e) {
    return e[qi] || null
}

var Of = [], rs = -1;

function Zr(e) {
    return {current: e}
}

function _e(e) {
    0 > rs || (e.current = Of[rs], Of[rs] = null, rs--)
}

function Ce(e, t) {
    rs++, Of[rs] = e.current, e.current = t
}

var Ur = {}, tt = Zr(Ur), yt = Zr(!1), Eo = Ur;

function Rs(e, t) {
    var n = e.type.contextTypes;
    if (!n) return Ur;
    var r = e.stateNode;
    if (r && r.__reactInternalMemoizedUnmaskedChildContext === t) return r.__reactInternalMemoizedMaskedChildContext;
    var o = {}, s;
    for (s in n) o[s] = t[s];
    return r && (e = e.stateNode, e.__reactInternalMemoizedUnmaskedChildContext = t, e.__reactInternalMemoizedMaskedChildContext = o), o
}

function xt(e) {
    return e = e.childContextTypes, e != null
}

function hc() {
    _e(yt), _e(tt)
}

function Pg(e, t, n) {
    if (tt.current !== Ur) throw Error(G(168));
    Ce(tt, t), Ce(yt, n)
}

function xx(e, t, n) {
    var r = e.stateNode;
    if (t = t.childContextTypes, typeof r.getChildContext != "function") return n;
    r = r.getChildContext();
    for (var o in r) if (!(o in t)) throw Error(G(108, Y2(e) || "Unknown", o));
    return Ae({}, n, r)
}

function mc(e) {
    return e = (e = e.stateNode) && e.__reactInternalMemoizedMergedChildContext || Ur, Eo = tt.current, Ce(tt, e), Ce(yt, yt.current), !0
}

function Tg(e, t, n) {
    var r = e.stateNode;
    if (!r) throw Error(G(169));
    n ? (e = xx(e, t, Eo), r.__reactInternalMemoizedMergedChildContext = e, _e(yt), _e(tt), Ce(tt, e)) : _e(yt), Ce(yt, n)
}

var Un = null, nu = !1, Ed = !1;

function wx(e) {
    Un === null ? Un = [e] : Un.push(e)
}

function u_(e) {
    nu = !0, wx(e)
}

function Jr() {
    if (!Ed && Un !== null) {
        Ed = !0;
        var e = 0, t = xe;
        try {
            var n = Un;
            for (xe = 1; e < n.length; e++) {
                var r = n[e];
                do r = r(!0); while (r !== null)
            }
            Un = null, nu = !1
        } catch (o) {
            throw Un !== null && (Un = Un.slice(e + 1)), Wy(Zp, Jr), o
        } finally {
            xe = t, Ed = !1
        }
    }
    return null
}

var os = [], ss = 0, gc = null, vc = 0, Ft = [], zt = 0, ko = null, Kn = 1, Yn = "";

function io(e, t) {
    os[ss++] = vc, os[ss++] = gc, gc = e, vc = t
}

function bx(e, t, n) {
    Ft[zt++] = Kn, Ft[zt++] = Yn, Ft[zt++] = ko, ko = e;
    var r = Kn;
    e = Yn;
    var o = 32 - ln(r) - 1;
    r &= ~(1 << o), n += 1;
    var s = 32 - ln(t) + o;
    if (30 < s) {
        var i = o - o % 5;
        s = (r & (1 << i) - 1).toString(32), r >>= i, o -= i, Kn = 1 << 32 - ln(t) + o | n << o | r, Yn = s + e
    } else Kn = 1 << s | n << o | r, Yn = e
}

function ah(e) {
    e.return !== null && (io(e, 1), bx(e, 1, 0))
}

function lh(e) {
    for (; e === gc;) gc = os[--ss], os[ss] = null, vc = os[--ss], os[ss] = null;
    for (; e === ko;) ko = Ft[--zt], Ft[zt] = null, Yn = Ft[--zt], Ft[zt] = null, Kn = Ft[--zt], Ft[zt] = null
}

var Tt = null, Pt = null, Pe = !1, on = null;

function Cx(e, t) {
    var n = Bt(5, null, null, 0);
    n.elementType = "DELETED", n.stateNode = t, n.return = e, t = e.deletions, t === null ? (e.deletions = [n], e.flags |= 16) : t.push(n)
}

function Mg(e, t) {
    switch (e.tag) {
        case 5:
            var n = e.type;
            return t = t.nodeType !== 1 || n.toLowerCase() !== t.nodeName.toLowerCase() ? null : t, t !== null ? (e.stateNode = t, Tt = e, Pt = $r(t.firstChild), !0) : !1;
        case 6:
            return t = e.pendingProps === "" || t.nodeType !== 3 ? null : t, t !== null ? (e.stateNode = t, Tt = e, Pt = null, !0) : !1;
        case 13:
            return t = t.nodeType !== 8 ? null : t, t !== null ? (n = ko !== null ? {
                id: Kn,
                overflow: Yn
            } : null, e.memoizedState = {
                dehydrated: t,
                treeContext: n,
                retryLane: 1073741824
            }, n = Bt(18, null, null, 0), n.stateNode = t, n.return = e, e.child = n, Tt = e, Pt = null, !0) : !1;
        default:
            return !1
    }
}

function Df(e) {
    return (e.mode & 1) !== 0 && (e.flags & 128) === 0
}

function $f(e) {
    if (Pe) {
        var t = Pt;
        if (t) {
            var n = t;
            if (!Mg(e, t)) {
                if (Df(e)) throw Error(G(418));
                t = $r(n.nextSibling);
                var r = Tt;
                t && Mg(e, t) ? Cx(r, n) : (e.flags = e.flags & -4097 | 2, Pe = !1, Tt = e)
            }
        } else {
            if (Df(e)) throw Error(G(418));
            e.flags = e.flags & -4097 | 2, Pe = !1, Tt = e
        }
    }
}

function Rg(e) {
    for (e = e.return; e !== null && e.tag !== 5 && e.tag !== 3 && e.tag !== 13;) e = e.return;
    Tt = e
}

function fl(e) {
    if (e !== Tt) return !1;
    if (!Pe) return Rg(e), Pe = !0, !1;
    var t;
    if ((t = e.tag !== 3) && !(t = e.tag !== 5) && (t = e.type, t = t !== "head" && t !== "body" && !Rf(e.type, e.memoizedProps)), t && (t = Pt)) {
        if (Df(e)) throw Sx(), Error(G(418));
        for (; t;) Cx(e, t), t = $r(t.nextSibling)
    }
    if (Rg(e), e.tag === 13) {
        if (e = e.memoizedState, e = e !== null ? e.dehydrated : null, !e) throw Error(G(317));
        e:{
            for (e = e.nextSibling, t = 0; e;) {
                if (e.nodeType === 8) {
                    var n = e.data;
                    if (n === "/$") {
                        if (t === 0) {
                            Pt = $r(e.nextSibling);
                            break e
                        }
                        t--
                    } else n !== "$" && n !== "$!" && n !== "$?" || t++
                }
                e = e.nextSibling
            }
            Pt = null
        }
    } else Pt = Tt ? $r(e.stateNode.nextSibling) : null;
    return !0
}

function Sx() {
    for (var e = Pt; e;) e = $r(e.nextSibling)
}

function As() {
    Pt = Tt = null, Pe = !1
}

function ch(e) {
    on === null ? on = [e] : on.push(e)
}

var d_ = or.ReactCurrentBatchConfig;

function fi(e, t, n) {
    if (e = n.ref, e !== null && typeof e != "function" && typeof e != "object") {
        if (n._owner) {
            if (n = n._owner, n) {
                if (n.tag !== 1) throw Error(G(309));
                var r = n.stateNode
            }
            if (!r) throw Error(G(147, e));
            var o = r, s = "" + e;
            return t !== null && t.ref !== null && typeof t.ref == "function" && t.ref._stringRef === s ? t.ref : (t = function (i) {
                var a = o.refs;
                i === null ? delete a[s] : a[s] = i
            }, t._stringRef = s, t)
        }
        if (typeof e != "string") throw Error(G(284));
        if (!n._owner) throw Error(G(290, e))
    }
    return e
}

function pl(e, t) {
    throw e = Object.prototype.toString.call(t), Error(G(31, e === "[object Object]" ? "object with keys {" + Object.keys(t).join(", ") + "}" : e))
}

function Ag(e) {
    var t = e._init;
    return t(e._payload)
}

function Nx(e) {
    function t(y, w) {
        if (e) {
            var v = y.deletions;
            v === null ? (y.deletions = [w], y.flags |= 16) : v.push(w)
        }
    }

    function n(y, w) {
        if (!e) return null;
        for (; w !== null;) t(y, w), w = w.sibling;
        return null
    }

    function r(y, w) {
        for (y = new Map; w !== null;) w.key !== null ? y.set(w.key, w) : y.set(w.index, w), w = w.sibling;
        return y
    }

    function o(y, w) {
        return y = Br(y, w), y.index = 0, y.sibling = null, y
    }

    function s(y, w, v) {
        return y.index = v, e ? (v = y.alternate, v !== null ? (v = v.index, v < w ? (y.flags |= 2, w) : v) : (y.flags |= 2, w)) : (y.flags |= 1048576, w)
    }

    function i(y) {
        return e && y.alternate === null && (y.flags |= 2), y
    }

    function a(y, w, v, C) {
        return w === null || w.tag !== 6 ? (w = Rd(v, y.mode, C), w.return = y, w) : (w = o(w, v), w.return = y, w)
    }

    function l(y, w, v, C) {
        var S = v.type;
        return S === Zo ? d(y, w, v.props.children, C, v.key) : w !== null && (w.elementType === S || typeof S == "object" && S !== null && S.$$typeof === br && Ag(S) === w.type) ? (C = o(w, v.props), C.ref = fi(y, w, v), C.return = y, C) : (C = Gl(v.type, v.key, v.props, null, y.mode, C), C.ref = fi(y, w, v), C.return = y, C)
    }

    function u(y, w, v, C) {
        return w === null || w.tag !== 4 || w.stateNode.containerInfo !== v.containerInfo || w.stateNode.implementation !== v.implementation ? (w = Ad(v, y.mode, C), w.return = y, w) : (w = o(w, v.children || []), w.return = y, w)
    }

    function d(y, w, v, C, S) {
        return w === null || w.tag !== 7 ? (w = wo(v, y.mode, C, S), w.return = y, w) : (w = o(w, v), w.return = y, w)
    }

    function f(y, w, v) {
        if (typeof w == "string" && w !== "" || typeof w == "number") return w = Rd("" + w, y.mode, v), w.return = y, w;
        if (typeof w == "object" && w !== null) {
            switch (w.$$typeof) {
                case nl:
                    return v = Gl(w.type, w.key, w.props, null, y.mode, v), v.ref = fi(y, null, w), v.return = y, v;
                case Qo:
                    return w = Ad(w, y.mode, v), w.return = y, w;
                case br:
                    var C = w._init;
                    return f(y, C(w._payload), v)
            }
            if (bi(w) || ai(w)) return w = wo(w, y.mode, v, null), w.return = y, w;
            pl(y, w)
        }
        return null
    }

    function p(y, w, v, C) {
        var S = w !== null ? w.key : null;
        if (typeof v == "string" && v !== "" || typeof v == "number") return S !== null ? null : a(y, w, "" + v, C);
        if (typeof v == "object" && v !== null) {
            switch (v.$$typeof) {
                case nl:
                    return v.key === S ? l(y, w, v, C) : null;
                case Qo:
                    return v.key === S ? u(y, w, v, C) : null;
                case br:
                    return S = v._init, p(y, w, S(v._payload), C)
            }
            if (bi(v) || ai(v)) return S !== null ? null : d(y, w, v, C, null);
            pl(y, v)
        }
        return null
    }

    function h(y, w, v, C, S) {
        if (typeof C == "string" && C !== "" || typeof C == "number") return y = y.get(v) || null, a(w, y, "" + C, S);
        if (typeof C == "object" && C !== null) {
            switch (C.$$typeof) {
                case nl:
                    return y = y.get(C.key === null ? v : C.key) || null, l(w, y, C, S);
                case Qo:
                    return y = y.get(C.key === null ? v : C.key) || null, u(w, y, C, S);
                case br:
                    var N = C._init;
                    return h(y, w, v, N(C._payload), S)
            }
            if (bi(C) || ai(C)) return y = y.get(v) || null, d(w, y, C, S, null);
            pl(w, C)
        }
        return null
    }

    function x(y, w, v, C) {
        for (var S = null, N = null, E = w, T = w = 0, O = null; E !== null && T < v.length; T++) {
            E.index > T ? (O = E, E = null) : O = E.sibling;
            var D = p(y, E, v[T], C);
            if (D === null) {
                E === null && (E = O);
                break
            }
            e && E && D.alternate === null && t(y, E), w = s(D, w, T), N === null ? S = D : N.sibling = D, N = D, E = O
        }
        if (T === v.length) return n(y, E), Pe && io(y, T), S;
        if (E === null) {
            for (; T < v.length; T++) E = f(y, v[T], C), E !== null && (w = s(E, w, T), N === null ? S = E : N.sibling = E, N = E);
            return Pe && io(y, T), S
        }
        for (E = r(y, E); T < v.length; T++) O = h(E, y, T, v[T], C), O !== null && (e && O.alternate !== null && E.delete(O.key === null ? T : O.key), w = s(O, w, T), N === null ? S = O : N.sibling = O, N = O);
        return e && E.forEach(function (L) {
            return t(y, L)
        }), Pe && io(y, T), S
    }

    function m(y, w, v, C) {
        var S = ai(v);
        if (typeof S != "function") throw Error(G(150));
        if (v = S.call(v), v == null) throw Error(G(151));
        for (var N = S = null, E = w, T = w = 0, O = null, D = v.next(); E !== null && !D.done; T++, D = v.next()) {
            E.index > T ? (O = E, E = null) : O = E.sibling;
            var L = p(y, E, D.value, C);
            if (L === null) {
                E === null && (E = O);
                break
            }
            e && E && L.alternate === null && t(y, E), w = s(L, w, T), N === null ? S = L : N.sibling = L, N = L, E = O
        }
        if (D.done) return n(y, E), Pe && io(y, T), S;
        if (E === null) {
            for (; !D.done; T++, D = v.next()) D = f(y, D.value, C), D !== null && (w = s(D, w, T), N === null ? S = D : N.sibling = D, N = D);
            return Pe && io(y, T), S
        }
        for (E = r(y, E); !D.done; T++, D = v.next()) D = h(E, y, T, D.value, C), D !== null && (e && D.alternate !== null && E.delete(D.key === null ? T : D.key), w = s(D, w, T), N === null ? S = D : N.sibling = D, N = D);
        return e && E.forEach(function ($) {
            return t(y, $)
        }), Pe && io(y, T), S
    }

    function b(y, w, v, C) {
        if (typeof v == "object" && v !== null && v.type === Zo && v.key === null && (v = v.props.children), typeof v == "object" && v !== null) {
            switch (v.$$typeof) {
                case nl:
                    e:{
                        for (var S = v.key, N = w; N !== null;) {
                            if (N.key === S) {
                                if (S = v.type, S === Zo) {
                                    if (N.tag === 7) {
                                        n(y, N.sibling), w = o(N, v.props.children), w.return = y, y = w;
                                        break e
                                    }
                                } else if (N.elementType === S || typeof S == "object" && S !== null && S.$$typeof === br && Ag(S) === N.type) {
                                    n(y, N.sibling), w = o(N, v.props), w.ref = fi(y, N, v), w.return = y, y = w;
                                    break e
                                }
                                n(y, N);
                                break
                            } else t(y, N);
                            N = N.sibling
                        }
                        v.type === Zo ? (w = wo(v.props.children, y.mode, C, v.key), w.return = y, y = w) : (C = Gl(v.type, v.key, v.props, null, y.mode, C), C.ref = fi(y, w, v), C.return = y, y = C)
                    }
                    return i(y);
                case Qo:
                    e:{
                        for (N = v.key; w !== null;) {
                            if (w.key === N) if (w.tag === 4 && w.stateNode.containerInfo === v.containerInfo && w.stateNode.implementation === v.implementation) {
                                n(y, w.sibling), w = o(w, v.children || []), w.return = y, y = w;
                                break e
                            } else {
                                n(y, w);
                                break
                            } else t(y, w);
                            w = w.sibling
                        }
                        w = Ad(v, y.mode, C), w.return = y, y = w
                    }
                    return i(y);
                case br:
                    return N = v._init, b(y, w, N(v._payload), C)
            }
            if (bi(v)) return x(y, w, v, C);
            if (ai(v)) return m(y, w, v, C);
            pl(y, v)
        }
        return typeof v == "string" && v !== "" || typeof v == "number" ? (v = "" + v, w !== null && w.tag === 6 ? (n(y, w.sibling), w = o(w, v), w.return = y, y = w) : (n(y, w), w = Rd(v, y.mode, C), w.return = y, y = w), i(y)) : n(y, w)
    }

    return b
}

var Is = Nx(!0), Ex = Nx(!1), yc = Zr(null), xc = null, is = null, uh = null;

function dh() {
    uh = is = xc = null
}

function fh(e) {
    var t = yc.current;
    _e(yc), e._currentValue = t
}

function Lf(e, t, n) {
    for (; e !== null;) {
        var r = e.alternate;
        if ((e.childLanes & t) !== t ? (e.childLanes |= t, r !== null && (r.childLanes |= t)) : r !== null && (r.childLanes & t) !== t && (r.childLanes |= t), e === n) break;
        e = e.return
    }
}

function ms(e, t) {
    xc = e, uh = is = null, e = e.dependencies, e !== null && e.firstContext !== null && (e.lanes & t && (gt = !0), e.firstContext = null)
}

function Kt(e) {
    var t = e._currentValue;
    if (uh !== e) if (e = {context: e, memoizedValue: t, next: null}, is === null) {
        if (xc === null) throw Error(G(308));
        is = e, xc.dependencies = {lanes: 0, firstContext: e}
    } else is = is.next = e;
    return t
}

var uo = null;

function ph(e) {
    uo === null ? uo = [e] : uo.push(e)
}

function kx(e, t, n, r) {
    var o = t.interleaved;
    return o === null ? (n.next = n, ph(t)) : (n.next = o.next, o.next = n), t.interleaved = n, Jn(e, r)
}

function Jn(e, t) {
    e.lanes |= t;
    var n = e.alternate;
    for (n !== null && (n.lanes |= t), n = e, e = e.return; e !== null;) e.childLanes |= t, n = e.alternate, n !== null && (n.childLanes |= t), n = e, e = e.return;
    return n.tag === 3 ? n.stateNode : null
}

var Cr = !1;

function hh(e) {
    e.updateQueue = {
        baseState: e.memoizedState,
        firstBaseUpdate: null,
        lastBaseUpdate: null,
        shared: {pending: null, interleaved: null, lanes: 0},
        effects: null
    }
}

function _x(e, t) {
    e = e.updateQueue, t.updateQueue === e && (t.updateQueue = {
        baseState: e.baseState,
        firstBaseUpdate: e.firstBaseUpdate,
        lastBaseUpdate: e.lastBaseUpdate,
        shared: e.shared,
        effects: e.effects
    })
}

function Xn(e, t) {
    return {eventTime: e, lane: t, tag: 0, payload: null, callback: null, next: null}
}

function Lr(e, t, n) {
    var r = e.updateQueue;
    if (r === null) return null;
    if (r = r.shared, he & 2) {
        var o = r.pending;
        return o === null ? t.next = t : (t.next = o.next, o.next = t), r.pending = t, Jn(e, n)
    }
    return o = r.interleaved, o === null ? (t.next = t, ph(r)) : (t.next = o.next, o.next = t), r.interleaved = t, Jn(e, n)
}

function Vl(e, t, n) {
    if (t = t.updateQueue, t !== null && (t = t.shared, (n & 4194240) !== 0)) {
        var r = t.lanes;
        r &= e.pendingLanes, n |= r, t.lanes = n, Jp(e, n)
    }
}

function Ig(e, t) {
    var n = e.updateQueue, r = e.alternate;
    if (r !== null && (r = r.updateQueue, n === r)) {
        var o = null, s = null;
        if (n = n.firstBaseUpdate, n !== null) {
            do {
                var i = {
                    eventTime: n.eventTime,
                    lane: n.lane,
                    tag: n.tag,
                    payload: n.payload,
                    callback: n.callback,
                    next: null
                };
                s === null ? o = s = i : s = s.next = i, n = n.next
            } while (n !== null);
            s === null ? o = s = t : s = s.next = t
        } else o = s = t;
        n = {
            baseState: r.baseState,
            firstBaseUpdate: o,
            lastBaseUpdate: s,
            shared: r.shared,
            effects: r.effects
        }, e.updateQueue = n;
        return
    }
    e = n.lastBaseUpdate, e === null ? n.firstBaseUpdate = t : e.next = t, n.lastBaseUpdate = t
}

function wc(e, t, n, r) {
    var o = e.updateQueue;
    Cr = !1;
    var s = o.firstBaseUpdate, i = o.lastBaseUpdate, a = o.shared.pending;
    if (a !== null) {
        o.shared.pending = null;
        var l = a, u = l.next;
        l.next = null, i === null ? s = u : i.next = u, i = l;
        var d = e.alternate;
        d !== null && (d = d.updateQueue, a = d.lastBaseUpdate, a !== i && (a === null ? d.firstBaseUpdate = u : a.next = u, d.lastBaseUpdate = l))
    }
    if (s !== null) {
        var f = o.baseState;
        i = 0, d = u = l = null, a = s;
        do {
            var p = a.lane, h = a.eventTime;
            if ((r & p) === p) {
                d !== null && (d = d.next = {
                    eventTime: h,
                    lane: 0,
                    tag: a.tag,
                    payload: a.payload,
                    callback: a.callback,
                    next: null
                });
                e:{
                    var x = e, m = a;
                    switch (p = t, h = n, m.tag) {
                        case 1:
                            if (x = m.payload, typeof x == "function") {
                                f = x.call(h, f, p);
                                break e
                            }
                            f = x;
                            break e;
                        case 3:
                            x.flags = x.flags & -65537 | 128;
                        case 0:
                            if (x = m.payload, p = typeof x == "function" ? x.call(h, f, p) : x, p == null) break e;
                            f = Ae({}, f, p);
                            break e;
                        case 2:
                            Cr = !0
                    }
                }
                a.callback !== null && a.lane !== 0 && (e.flags |= 64, p = o.effects, p === null ? o.effects = [a] : p.push(a))
            } else h = {
                eventTime: h,
                lane: p,
                tag: a.tag,
                payload: a.payload,
                callback: a.callback,
                next: null
            }, d === null ? (u = d = h, l = f) : d = d.next = h, i |= p;
            if (a = a.next, a === null) {
                if (a = o.shared.pending, a === null) break;
                p = a, a = p.next, p.next = null, o.lastBaseUpdate = p, o.shared.pending = null
            }
        } while (!0);
        if (d === null && (l = f), o.baseState = l, o.firstBaseUpdate = u, o.lastBaseUpdate = d, t = o.shared.interleaved, t !== null) {
            o = t;
            do i |= o.lane, o = o.next; while (o !== t)
        } else s === null && (o.shared.lanes = 0);
        jo |= i, e.lanes = i, e.memoizedState = f
    }
}

function Og(e, t, n) {
    if (e = t.effects, t.effects = null, e !== null) for (t = 0; t < e.length; t++) {
        var r = e[t], o = r.callback;
        if (o !== null) {
            if (r.callback = null, r = n, typeof o != "function") throw Error(G(191, o));
            o.call(r)
        }
    }
}

var ka = {}, Tn = Zr(ka), Qi = Zr(ka), Zi = Zr(ka);

function fo(e) {
    if (e === ka) throw Error(G(174));
    return e
}

function mh(e, t) {
    switch (Ce(Zi, t), Ce(Qi, e), Ce(Tn, ka), e = t.nodeType, e) {
        case 9:
        case 11:
            t = (t = t.documentElement) ? t.namespaceURI : yf(null, "");
            break;
        default:
            e = e === 8 ? t.parentNode : t, t = e.namespaceURI || null, e = e.tagName, t = yf(t, e)
    }
    _e(Tn), Ce(Tn, t)
}

function Os() {
    _e(Tn), _e(Qi), _e(Zi)
}

function jx(e) {
    fo(Zi.current);
    var t = fo(Tn.current), n = yf(t, e.type);
    t !== n && (Ce(Qi, e), Ce(Tn, n))
}

function gh(e) {
    Qi.current === e && (_e(Tn), _e(Qi))
}

var Me = Zr(0);

function bc(e) {
    for (var t = e; t !== null;) {
        if (t.tag === 13) {
            var n = t.memoizedState;
            if (n !== null && (n = n.dehydrated, n === null || n.data === "$?" || n.data === "$!")) return t
        } else if (t.tag === 19 && t.memoizedProps.revealOrder !== void 0) {
            if (t.flags & 128) return t
        } else if (t.child !== null) {
            t.child.return = t, t = t.child;
            continue
        }
        if (t === e) break;
        for (; t.sibling === null;) {
            if (t.return === null || t.return === e) return null;
            t = t.return
        }
        t.sibling.return = t.return, t = t.sibling
    }
    return null
}

var kd = [];

function vh() {
    for (var e = 0; e < kd.length; e++) kd[e]._workInProgressVersionPrimary = null;
    kd.length = 0
}

var Hl = or.ReactCurrentDispatcher, _d = or.ReactCurrentBatchConfig, _o = 0, Re = null, Ve = null, Ue = null, Cc = !1,
    Ai = !1, Ji = 0, f_ = 0;

function Qe() {
    throw Error(G(321))
}

function yh(e, t) {
    if (t === null) return !1;
    for (var n = 0; n < t.length && n < e.length; n++) if (!fn(e[n], t[n])) return !1;
    return !0
}

function xh(e, t, n, r, o, s) {
    if (_o = s, Re = t, t.memoizedState = null, t.updateQueue = null, t.lanes = 0, Hl.current = e === null || e.memoizedState === null ? g_ : v_, e = n(r, o), Ai) {
        s = 0;
        do {
            if (Ai = !1, Ji = 0, 25 <= s) throw Error(G(301));
            s += 1, Ue = Ve = null, t.updateQueue = null, Hl.current = y_, e = n(r, o)
        } while (Ai)
    }
    if (Hl.current = Sc, t = Ve !== null && Ve.next !== null, _o = 0, Ue = Ve = Re = null, Cc = !1, t) throw Error(G(300));
    return e
}

function wh() {
    var e = Ji !== 0;
    return Ji = 0, e
}

function Sn() {
    var e = {memoizedState: null, baseState: null, baseQueue: null, queue: null, next: null};
    return Ue === null ? Re.memoizedState = Ue = e : Ue = Ue.next = e, Ue
}

function Yt() {
    if (Ve === null) {
        var e = Re.alternate;
        e = e !== null ? e.memoizedState : null
    } else e = Ve.next;
    var t = Ue === null ? Re.memoizedState : Ue.next;
    if (t !== null) Ue = t, Ve = e; else {
        if (e === null) throw Error(G(310));
        Ve = e, e = {
            memoizedState: Ve.memoizedState,
            baseState: Ve.baseState,
            baseQueue: Ve.baseQueue,
            queue: Ve.queue,
            next: null
        }, Ue === null ? Re.memoizedState = Ue = e : Ue = Ue.next = e
    }
    return Ue
}

function ea(e, t) {
    return typeof t == "function" ? t(e) : t
}

function jd(e) {
    var t = Yt(), n = t.queue;
    if (n === null) throw Error(G(311));
    n.lastRenderedReducer = e;
    var r = Ve, o = r.baseQueue, s = n.pending;
    if (s !== null) {
        if (o !== null) {
            var i = o.next;
            o.next = s.next, s.next = i
        }
        r.baseQueue = o = s, n.pending = null
    }
    if (o !== null) {
        s = o.next, r = r.baseState;
        var a = i = null, l = null, u = s;
        do {
            var d = u.lane;
            if ((_o & d) === d) l !== null && (l = l.next = {
                lane: 0,
                action: u.action,
                hasEagerState: u.hasEagerState,
                eagerState: u.eagerState,
                next: null
            }), r = u.hasEagerState ? u.eagerState : e(r, u.action); else {
                var f = {
                    lane: d,
                    action: u.action,
                    hasEagerState: u.hasEagerState,
                    eagerState: u.eagerState,
                    next: null
                };
                l === null ? (a = l = f, i = r) : l = l.next = f, Re.lanes |= d, jo |= d
            }
            u = u.next
        } while (u !== null && u !== s);
        l === null ? i = r : l.next = a, fn(r, t.memoizedState) || (gt = !0), t.memoizedState = r, t.baseState = i, t.baseQueue = l, n.lastRenderedState = r
    }
    if (e = n.interleaved, e !== null) {
        o = e;
        do s = o.lane, Re.lanes |= s, jo |= s, o = o.next; while (o !== e)
    } else o === null && (n.lanes = 0);
    return [t.memoizedState, n.dispatch]
}

function Pd(e) {
    var t = Yt(), n = t.queue;
    if (n === null) throw Error(G(311));
    n.lastRenderedReducer = e;
    var r = n.dispatch, o = n.pending, s = t.memoizedState;
    if (o !== null) {
        n.pending = null;
        var i = o = o.next;
        do s = e(s, i.action), i = i.next; while (i !== o);
        fn(s, t.memoizedState) || (gt = !0), t.memoizedState = s, t.baseQueue === null && (t.baseState = s), n.lastRenderedState = s
    }
    return [s, r]
}

function Px() {
}

function Tx(e, t) {
    var n = Re, r = Yt(), o = t(), s = !fn(r.memoizedState, o);
    if (s && (r.memoizedState = o, gt = !0), r = r.queue, bh(Ax.bind(null, n, r, e), [e]), r.getSnapshot !== t || s || Ue !== null && Ue.memoizedState.tag & 1) {
        if (n.flags |= 2048, ta(9, Rx.bind(null, n, r, o, t), void 0, null), We === null) throw Error(G(349));
        _o & 30 || Mx(n, t, o)
    }
    return o
}

function Mx(e, t, n) {
    e.flags |= 16384, e = {getSnapshot: t, value: n}, t = Re.updateQueue, t === null ? (t = {
        lastEffect: null,
        stores: null
    }, Re.updateQueue = t, t.stores = [e]) : (n = t.stores, n === null ? t.stores = [e] : n.push(e))
}

function Rx(e, t, n, r) {
    t.value = n, t.getSnapshot = r, Ix(t) && Ox(e)
}

function Ax(e, t, n) {
    return n(function () {
        Ix(t) && Ox(e)
    })
}

function Ix(e) {
    var t = e.getSnapshot;
    e = e.value;
    try {
        var n = t();
        return !fn(e, n)
    } catch {
        return !0
    }
}

function Ox(e) {
    var t = Jn(e, 1);
    t !== null && cn(t, e, 1, -1)
}

function Dg(e) {
    var t = Sn();
    return typeof e == "function" && (e = e()), t.memoizedState = t.baseState = e, e = {
        pending: null,
        interleaved: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: ea,
        lastRenderedState: e
    }, t.queue = e, e = e.dispatch = m_.bind(null, Re, e), [t.memoizedState, e]
}

function ta(e, t, n, r) {
    return e = {
        tag: e,
        create: t,
        destroy: n,
        deps: r,
        next: null
    }, t = Re.updateQueue, t === null ? (t = {
        lastEffect: null,
        stores: null
    }, Re.updateQueue = t, t.lastEffect = e.next = e) : (n = t.lastEffect, n === null ? t.lastEffect = e.next = e : (r = n.next, n.next = e, e.next = r, t.lastEffect = e)), e
}

function Dx() {
    return Yt().memoizedState
}

function Ul(e, t, n, r) {
    var o = Sn();
    Re.flags |= e, o.memoizedState = ta(1 | t, n, void 0, r === void 0 ? null : r)
}

function ru(e, t, n, r) {
    var o = Yt();
    r = r === void 0 ? null : r;
    var s = void 0;
    if (Ve !== null) {
        var i = Ve.memoizedState;
        if (s = i.destroy, r !== null && yh(r, i.deps)) {
            o.memoizedState = ta(t, n, s, r);
            return
        }
    }
    Re.flags |= e, o.memoizedState = ta(1 | t, n, s, r)
}

function $g(e, t) {
    return Ul(8390656, 8, e, t)
}

function bh(e, t) {
    return ru(2048, 8, e, t)
}

function $x(e, t) {
    return ru(4, 2, e, t)
}

function Lx(e, t) {
    return ru(4, 4, e, t)
}

function Fx(e, t) {
    if (typeof t == "function") return e = e(), t(e), function () {
        t(null)
    };
    if (t != null) return e = e(), t.current = e, function () {
        t.current = null
    }
}

function zx(e, t, n) {
    return n = n != null ? n.concat([e]) : null, ru(4, 4, Fx.bind(null, t, e), n)
}

function Ch() {
}

function Bx(e, t) {
    var n = Yt();
    t = t === void 0 ? null : t;
    var r = n.memoizedState;
    return r !== null && t !== null && yh(t, r[1]) ? r[0] : (n.memoizedState = [e, t], e)
}

function Vx(e, t) {
    var n = Yt();
    t = t === void 0 ? null : t;
    var r = n.memoizedState;
    return r !== null && t !== null && yh(t, r[1]) ? r[0] : (e = e(), n.memoizedState = [e, t], e)
}

function Hx(e, t, n) {
    return _o & 21 ? (fn(n, t) || (n = Gy(), Re.lanes |= n, jo |= n, e.baseState = !0), t) : (e.baseState && (e.baseState = !1, gt = !0), e.memoizedState = n)
}

function p_(e, t) {
    var n = xe;
    xe = n !== 0 && 4 > n ? n : 4, e(!0);
    var r = _d.transition;
    _d.transition = {};
    try {
        e(!1), t()
    } finally {
        xe = n, _d.transition = r
    }
}

function Ux() {
    return Yt().memoizedState
}

function h_(e, t, n) {
    var r = zr(e);
    if (n = {
        lane: r,
        action: n,
        hasEagerState: !1,
        eagerState: null,
        next: null
    }, Wx(e)) Kx(t, n); else if (n = kx(e, t, n, r), n !== null) {
        var o = lt();
        cn(n, e, r, o), Yx(n, t, r)
    }
}

function m_(e, t, n) {
    var r = zr(e), o = {lane: r, action: n, hasEagerState: !1, eagerState: null, next: null};
    if (Wx(e)) Kx(t, o); else {
        var s = e.alternate;
        if (e.lanes === 0 && (s === null || s.lanes === 0) && (s = t.lastRenderedReducer, s !== null)) try {
            var i = t.lastRenderedState, a = s(i, n);
            if (o.hasEagerState = !0, o.eagerState = a, fn(a, i)) {
                var l = t.interleaved;
                l === null ? (o.next = o, ph(t)) : (o.next = l.next, l.next = o), t.interleaved = o;
                return
            }
        } catch {
        } finally {
        }
        n = kx(e, t, o, r), n !== null && (o = lt(), cn(n, e, r, o), Yx(n, t, r))
    }
}

function Wx(e) {
    var t = e.alternate;
    return e === Re || t !== null && t === Re
}

function Kx(e, t) {
    Ai = Cc = !0;
    var n = e.pending;
    n === null ? t.next = t : (t.next = n.next, n.next = t), e.pending = t
}

function Yx(e, t, n) {
    if (n & 4194240) {
        var r = t.lanes;
        r &= e.pendingLanes, n |= r, t.lanes = n, Jp(e, n)
    }
}

var Sc = {
    readContext: Kt,
    useCallback: Qe,
    useContext: Qe,
    useEffect: Qe,
    useImperativeHandle: Qe,
    useInsertionEffect: Qe,
    useLayoutEffect: Qe,
    useMemo: Qe,
    useReducer: Qe,
    useRef: Qe,
    useState: Qe,
    useDebugValue: Qe,
    useDeferredValue: Qe,
    useTransition: Qe,
    useMutableSource: Qe,
    useSyncExternalStore: Qe,
    useId: Qe,
    unstable_isNewReconciler: !1
}, g_ = {
    readContext: Kt, useCallback: function (e, t) {
        return Sn().memoizedState = [e, t === void 0 ? null : t], e
    }, useContext: Kt, useEffect: $g, useImperativeHandle: function (e, t, n) {
        return n = n != null ? n.concat([e]) : null, Ul(4194308, 4, Fx.bind(null, t, e), n)
    }, useLayoutEffect: function (e, t) {
        return Ul(4194308, 4, e, t)
    }, useInsertionEffect: function (e, t) {
        return Ul(4, 2, e, t)
    }, useMemo: function (e, t) {
        var n = Sn();
        return t = t === void 0 ? null : t, e = e(), n.memoizedState = [e, t], e
    }, useReducer: function (e, t, n) {
        var r = Sn();
        return t = n !== void 0 ? n(t) : t, r.memoizedState = r.baseState = t, e = {
            pending: null,
            interleaved: null,
            lanes: 0,
            dispatch: null,
            lastRenderedReducer: e,
            lastRenderedState: t
        }, r.queue = e, e = e.dispatch = h_.bind(null, Re, e), [r.memoizedState, e]
    }, useRef: function (e) {
        var t = Sn();
        return e = {current: e}, t.memoizedState = e
    }, useState: Dg, useDebugValue: Ch, useDeferredValue: function (e) {
        return Sn().memoizedState = e
    }, useTransition: function () {
        var e = Dg(!1), t = e[0];
        return e = p_.bind(null, e[1]), Sn().memoizedState = e, [t, e]
    }, useMutableSource: function () {
    }, useSyncExternalStore: function (e, t, n) {
        var r = Re, o = Sn();
        if (Pe) {
            if (n === void 0) throw Error(G(407));
            n = n()
        } else {
            if (n = t(), We === null) throw Error(G(349));
            _o & 30 || Mx(r, t, n)
        }
        o.memoizedState = n;
        var s = {value: n, getSnapshot: t};
        return o.queue = s, $g(Ax.bind(null, r, s, e), [e]), r.flags |= 2048, ta(9, Rx.bind(null, r, s, n, t), void 0, null), n
    }, useId: function () {
        var e = Sn(), t = We.identifierPrefix;
        if (Pe) {
            var n = Yn, r = Kn;
            n = (r & ~(1 << 32 - ln(r) - 1)).toString(32) + n, t = ":" + t + "R" + n, n = Ji++, 0 < n && (t += "H" + n.toString(32)), t += ":"
        } else n = f_++, t = ":" + t + "r" + n.toString(32) + ":";
        return e.memoizedState = t
    }, unstable_isNewReconciler: !1
}, v_ = {
    readContext: Kt,
    useCallback: Bx,
    useContext: Kt,
    useEffect: bh,
    useImperativeHandle: zx,
    useInsertionEffect: $x,
    useLayoutEffect: Lx,
    useMemo: Vx,
    useReducer: jd,
    useRef: Dx,
    useState: function () {
        return jd(ea)
    },
    useDebugValue: Ch,
    useDeferredValue: function (e) {
        var t = Yt();
        return Hx(t, Ve.memoizedState, e)
    },
    useTransition: function () {
        var e = jd(ea)[0], t = Yt().memoizedState;
        return [e, t]
    },
    useMutableSource: Px,
    useSyncExternalStore: Tx,
    useId: Ux,
    unstable_isNewReconciler: !1
}, y_ = {
    readContext: Kt,
    useCallback: Bx,
    useContext: Kt,
    useEffect: bh,
    useImperativeHandle: zx,
    useInsertionEffect: $x,
    useLayoutEffect: Lx,
    useMemo: Vx,
    useReducer: Pd,
    useRef: Dx,
    useState: function () {
        return Pd(ea)
    },
    useDebugValue: Ch,
    useDeferredValue: function (e) {
        var t = Yt();
        return Ve === null ? t.memoizedState = e : Hx(t, Ve.memoizedState, e)
    },
    useTransition: function () {
        var e = Pd(ea)[0], t = Yt().memoizedState;
        return [e, t]
    },
    useMutableSource: Px,
    useSyncExternalStore: Tx,
    useId: Ux,
    unstable_isNewReconciler: !1
};

function Zt(e, t) {
    if (e && e.defaultProps) {
        t = Ae({}, t), e = e.defaultProps;
        for (var n in e) t[n] === void 0 && (t[n] = e[n]);
        return t
    }
    return t
}

function Ff(e, t, n, r) {
    t = e.memoizedState, n = n(r, t), n = n == null ? t : Ae({}, t, n), e.memoizedState = n, e.lanes === 0 && (e.updateQueue.baseState = n)
}

var ou = {
    isMounted: function (e) {
        return (e = e._reactInternals) ? Lo(e) === e : !1
    }, enqueueSetState: function (e, t, n) {
        e = e._reactInternals;
        var r = lt(), o = zr(e), s = Xn(r, o);
        s.payload = t, n != null && (s.callback = n), t = Lr(e, s, o), t !== null && (cn(t, e, o, r), Vl(t, e, o))
    }, enqueueReplaceState: function (e, t, n) {
        e = e._reactInternals;
        var r = lt(), o = zr(e), s = Xn(r, o);
        s.tag = 1, s.payload = t, n != null && (s.callback = n), t = Lr(e, s, o), t !== null && (cn(t, e, o, r), Vl(t, e, o))
    }, enqueueForceUpdate: function (e, t) {
        e = e._reactInternals;
        var n = lt(), r = zr(e), o = Xn(n, r);
        o.tag = 2, t != null && (o.callback = t), t = Lr(e, o, r), t !== null && (cn(t, e, r, n), Vl(t, e, r))
    }
};

function Lg(e, t, n, r, o, s, i) {
    return e = e.stateNode, typeof e.shouldComponentUpdate == "function" ? e.shouldComponentUpdate(r, s, i) : t.prototype && t.prototype.isPureReactComponent ? !Yi(n, r) || !Yi(o, s) : !0
}

function Gx(e, t, n) {
    var r = !1, o = Ur, s = t.contextType;
    return typeof s == "object" && s !== null ? s = Kt(s) : (o = xt(t) ? Eo : tt.current, r = t.contextTypes, s = (r = r != null) ? Rs(e, o) : Ur), t = new t(n, s), e.memoizedState = t.state !== null && t.state !== void 0 ? t.state : null, t.updater = ou, e.stateNode = t, t._reactInternals = e, r && (e = e.stateNode, e.__reactInternalMemoizedUnmaskedChildContext = o, e.__reactInternalMemoizedMaskedChildContext = s), t
}

function Fg(e, t, n, r) {
    e = t.state, typeof t.componentWillReceiveProps == "function" && t.componentWillReceiveProps(n, r), typeof t.UNSAFE_componentWillReceiveProps == "function" && t.UNSAFE_componentWillReceiveProps(n, r), t.state !== e && ou.enqueueReplaceState(t, t.state, null)
}

function zf(e, t, n, r) {
    var o = e.stateNode;
    o.props = n, o.state = e.memoizedState, o.refs = {}, hh(e);
    var s = t.contextType;
    typeof s == "object" && s !== null ? o.context = Kt(s) : (s = xt(t) ? Eo : tt.current, o.context = Rs(e, s)), o.state = e.memoizedState, s = t.getDerivedStateFromProps, typeof s == "function" && (Ff(e, t, s, n), o.state = e.memoizedState), typeof t.getDerivedStateFromProps == "function" || typeof o.getSnapshotBeforeUpdate == "function" || typeof o.UNSAFE_componentWillMount != "function" && typeof o.componentWillMount != "function" || (t = o.state, typeof o.componentWillMount == "function" && o.componentWillMount(), typeof o.UNSAFE_componentWillMount == "function" && o.UNSAFE_componentWillMount(), t !== o.state && ou.enqueueReplaceState(o, o.state, null), wc(e, n, o, r), o.state = e.memoizedState), typeof o.componentDidMount == "function" && (e.flags |= 4194308)
}

function Ds(e, t) {
    try {
        var n = "", r = t;
        do n += K2(r), r = r.return; while (r);
        var o = n
    } catch (s) {
        o = `
Error generating stack: ` + s.message + `
` + s.stack
    }
    return {value: e, source: t, stack: o, digest: null}
}

function Td(e, t, n) {
    return {value: e, source: null, stack: n ?? null, digest: t ?? null}
}

function Bf(e, t) {
    try {
        console.error(t.value)
    } catch (n) {
        setTimeout(function () {
            throw n
        })
    }
}

var x_ = typeof WeakMap == "function" ? WeakMap : Map;

function Xx(e, t, n) {
    n = Xn(-1, n), n.tag = 3, n.payload = {element: null};
    var r = t.value;
    return n.callback = function () {
        Ec || (Ec = !0, Qf = r), Bf(e, t)
    }, n
}

function qx(e, t, n) {
    n = Xn(-1, n), n.tag = 3;
    var r = e.type.getDerivedStateFromError;
    if (typeof r == "function") {
        var o = t.value;
        n.payload = function () {
            return r(o)
        }, n.callback = function () {
            Bf(e, t)
        }
    }
    var s = e.stateNode;
    return s !== null && typeof s.componentDidCatch == "function" && (n.callback = function () {
        Bf(e, t), typeof r != "function" && (Fr === null ? Fr = new Set([this]) : Fr.add(this));
        var i = t.stack;
        this.componentDidCatch(t.value, {componentStack: i !== null ? i : ""})
    }), n
}

function zg(e, t, n) {
    var r = e.pingCache;
    if (r === null) {
        r = e.pingCache = new x_;
        var o = new Set;
        r.set(t, o)
    } else o = r.get(t), o === void 0 && (o = new Set, r.set(t, o));
    o.has(n) || (o.add(n), e = A_.bind(null, e, t, n), t.then(e, e))
}

function Bg(e) {
    do {
        var t;
        if ((t = e.tag === 13) && (t = e.memoizedState, t = t !== null ? t.dehydrated !== null : !0), t) return e;
        e = e.return
    } while (e !== null);
    return null
}

function Vg(e, t, n, r, o) {
    return e.mode & 1 ? (e.flags |= 65536, e.lanes = o, e) : (e === t ? e.flags |= 65536 : (e.flags |= 128, n.flags |= 131072, n.flags &= -52805, n.tag === 1 && (n.alternate === null ? n.tag = 17 : (t = Xn(-1, 1), t.tag = 2, Lr(n, t, 1))), n.lanes |= 1), e)
}

var w_ = or.ReactCurrentOwner, gt = !1;

function st(e, t, n, r) {
    t.child = e === null ? Ex(t, null, n, r) : Is(t, e.child, n, r)
}

function Hg(e, t, n, r, o) {
    n = n.render;
    var s = t.ref;
    return ms(t, o), r = xh(e, t, n, r, s, o), n = wh(), e !== null && !gt ? (t.updateQueue = e.updateQueue, t.flags &= -2053, e.lanes &= ~o, er(e, t, o)) : (Pe && n && ah(t), t.flags |= 1, st(e, t, r, o), t.child)
}

function Ug(e, t, n, r, o) {
    if (e === null) {
        var s = n.type;
        return typeof s == "function" && !Th(s) && s.defaultProps === void 0 && n.compare === null && n.defaultProps === void 0 ? (t.tag = 15, t.type = s, Qx(e, t, s, r, o)) : (e = Gl(n.type, null, r, t, t.mode, o), e.ref = t.ref, e.return = t, t.child = e)
    }
    if (s = e.child, !(e.lanes & o)) {
        var i = s.memoizedProps;
        if (n = n.compare, n = n !== null ? n : Yi, n(i, r) && e.ref === t.ref) return er(e, t, o)
    }
    return t.flags |= 1, e = Br(s, r), e.ref = t.ref, e.return = t, t.child = e
}

function Qx(e, t, n, r, o) {
    if (e !== null) {
        var s = e.memoizedProps;
        if (Yi(s, r) && e.ref === t.ref) if (gt = !1, t.pendingProps = r = s, (e.lanes & o) !== 0) e.flags & 131072 && (gt = !0); else return t.lanes = e.lanes, er(e, t, o)
    }
    return Vf(e, t, n, r, o)
}

function Zx(e, t, n) {
    var r = t.pendingProps, o = r.children, s = e !== null ? e.memoizedState : null;
    if (r.mode === "hidden") if (!(t.mode & 1)) t.memoizedState = {
        baseLanes: 0,
        cachePool: null,
        transitions: null
    }, Ce(ls, kt), kt |= n; else {
        if (!(n & 1073741824)) return e = s !== null ? s.baseLanes | n : n, t.lanes = t.childLanes = 1073741824, t.memoizedState = {
            baseLanes: e,
            cachePool: null,
            transitions: null
        }, t.updateQueue = null, Ce(ls, kt), kt |= e, null;
        t.memoizedState = {
            baseLanes: 0,
            cachePool: null,
            transitions: null
        }, r = s !== null ? s.baseLanes : n, Ce(ls, kt), kt |= r
    } else s !== null ? (r = s.baseLanes | n, t.memoizedState = null) : r = n, Ce(ls, kt), kt |= r;
    return st(e, t, o, n), t.child
}

function Jx(e, t) {
    var n = t.ref;
    (e === null && n !== null || e !== null && e.ref !== n) && (t.flags |= 512, t.flags |= 2097152)
}

function Vf(e, t, n, r, o) {
    var s = xt(n) ? Eo : tt.current;
    return s = Rs(t, s), ms(t, o), n = xh(e, t, n, r, s, o), r = wh(), e !== null && !gt ? (t.updateQueue = e.updateQueue, t.flags &= -2053, e.lanes &= ~o, er(e, t, o)) : (Pe && r && ah(t), t.flags |= 1, st(e, t, n, o), t.child)
}

function Wg(e, t, n, r, o) {
    if (xt(n)) {
        var s = !0;
        mc(t)
    } else s = !1;
    if (ms(t, o), t.stateNode === null) Wl(e, t), Gx(t, n, r), zf(t, n, r, o), r = !0; else if (e === null) {
        var i = t.stateNode, a = t.memoizedProps;
        i.props = a;
        var l = i.context, u = n.contextType;
        typeof u == "object" && u !== null ? u = Kt(u) : (u = xt(n) ? Eo : tt.current, u = Rs(t, u));
        var d = n.getDerivedStateFromProps,
            f = typeof d == "function" || typeof i.getSnapshotBeforeUpdate == "function";
        f || typeof i.UNSAFE_componentWillReceiveProps != "function" && typeof i.componentWillReceiveProps != "function" || (a !== r || l !== u) && Fg(t, i, r, u), Cr = !1;
        var p = t.memoizedState;
        i.state = p, wc(t, r, i, o), l = t.memoizedState, a !== r || p !== l || yt.current || Cr ? (typeof d == "function" && (Ff(t, n, d, r), l = t.memoizedState), (a = Cr || Lg(t, n, a, r, p, l, u)) ? (f || typeof i.UNSAFE_componentWillMount != "function" && typeof i.componentWillMount != "function" || (typeof i.componentWillMount == "function" && i.componentWillMount(), typeof i.UNSAFE_componentWillMount == "function" && i.UNSAFE_componentWillMount()), typeof i.componentDidMount == "function" && (t.flags |= 4194308)) : (typeof i.componentDidMount == "function" && (t.flags |= 4194308), t.memoizedProps = r, t.memoizedState = l), i.props = r, i.state = l, i.context = u, r = a) : (typeof i.componentDidMount == "function" && (t.flags |= 4194308), r = !1)
    } else {
        i = t.stateNode, _x(e, t), a = t.memoizedProps, u = t.type === t.elementType ? a : Zt(t.type, a), i.props = u, f = t.pendingProps, p = i.context, l = n.contextType, typeof l == "object" && l !== null ? l = Kt(l) : (l = xt(n) ? Eo : tt.current, l = Rs(t, l));
        var h = n.getDerivedStateFromProps;
        (d = typeof h == "function" || typeof i.getSnapshotBeforeUpdate == "function") || typeof i.UNSAFE_componentWillReceiveProps != "function" && typeof i.componentWillReceiveProps != "function" || (a !== f || p !== l) && Fg(t, i, r, l), Cr = !1, p = t.memoizedState, i.state = p, wc(t, r, i, o);
        var x = t.memoizedState;
        a !== f || p !== x || yt.current || Cr ? (typeof h == "function" && (Ff(t, n, h, r), x = t.memoizedState), (u = Cr || Lg(t, n, u, r, p, x, l) || !1) ? (d || typeof i.UNSAFE_componentWillUpdate != "function" && typeof i.componentWillUpdate != "function" || (typeof i.componentWillUpdate == "function" && i.componentWillUpdate(r, x, l), typeof i.UNSAFE_componentWillUpdate == "function" && i.UNSAFE_componentWillUpdate(r, x, l)), typeof i.componentDidUpdate == "function" && (t.flags |= 4), typeof i.getSnapshotBeforeUpdate == "function" && (t.flags |= 1024)) : (typeof i.componentDidUpdate != "function" || a === e.memoizedProps && p === e.memoizedState || (t.flags |= 4), typeof i.getSnapshotBeforeUpdate != "function" || a === e.memoizedProps && p === e.memoizedState || (t.flags |= 1024), t.memoizedProps = r, t.memoizedState = x), i.props = r, i.state = x, i.context = l, r = u) : (typeof i.componentDidUpdate != "function" || a === e.memoizedProps && p === e.memoizedState || (t.flags |= 4), typeof i.getSnapshotBeforeUpdate != "function" || a === e.memoizedProps && p === e.memoizedState || (t.flags |= 1024), r = !1)
    }
    return Hf(e, t, n, r, s, o)
}

function Hf(e, t, n, r, o, s) {
    Jx(e, t);
    var i = (t.flags & 128) !== 0;
    if (!r && !i) return o && Tg(t, n, !1), er(e, t, s);
    r = t.stateNode, w_.current = t;
    var a = i && typeof n.getDerivedStateFromError != "function" ? null : r.render();
    return t.flags |= 1, e !== null && i ? (t.child = Is(t, e.child, null, s), t.child = Is(t, null, a, s)) : st(e, t, a, s), t.memoizedState = r.state, o && Tg(t, n, !0), t.child
}

function ew(e) {
    var t = e.stateNode;
    t.pendingContext ? Pg(e, t.pendingContext, t.pendingContext !== t.context) : t.context && Pg(e, t.context, !1), mh(e, t.containerInfo)
}

function Kg(e, t, n, r, o) {
    return As(), ch(o), t.flags |= 256, st(e, t, n, r), t.child
}

var Uf = {dehydrated: null, treeContext: null, retryLane: 0};

function Wf(e) {
    return {baseLanes: e, cachePool: null, transitions: null}
}

function tw(e, t, n) {
    var r = t.pendingProps, o = Me.current, s = !1, i = (t.flags & 128) !== 0, a;
    if ((a = i) || (a = e !== null && e.memoizedState === null ? !1 : (o & 2) !== 0), a ? (s = !0, t.flags &= -129) : (e === null || e.memoizedState !== null) && (o |= 1), Ce(Me, o & 1), e === null) return $f(t), e = t.memoizedState, e !== null && (e = e.dehydrated, e !== null) ? (t.mode & 1 ? e.data === "$!" ? t.lanes = 8 : t.lanes = 1073741824 : t.lanes = 1, null) : (i = r.children, e = r.fallback, s ? (r = t.mode, s = t.child, i = {
        mode: "hidden",
        children: i
    }, !(r & 1) && s !== null ? (s.childLanes = 0, s.pendingProps = i) : s = au(i, r, 0, null), e = wo(e, r, n, null), s.return = t, e.return = t, s.sibling = e, t.child = s, t.child.memoizedState = Wf(n), t.memoizedState = Uf, e) : Sh(t, i));
    if (o = e.memoizedState, o !== null && (a = o.dehydrated, a !== null)) return b_(e, t, i, r, a, o, n);
    if (s) {
        s = r.fallback, i = t.mode, o = e.child, a = o.sibling;
        var l = {mode: "hidden", children: r.children};
        return !(i & 1) && t.child !== o ? (r = t.child, r.childLanes = 0, r.pendingProps = l, t.deletions = null) : (r = Br(o, l), r.subtreeFlags = o.subtreeFlags & 14680064), a !== null ? s = Br(a, s) : (s = wo(s, i, n, null), s.flags |= 2), s.return = t, r.return = t, r.sibling = s, t.child = r, r = s, s = t.child, i = e.child.memoizedState, i = i === null ? Wf(n) : {
            baseLanes: i.baseLanes | n,
            cachePool: null,
            transitions: i.transitions
        }, s.memoizedState = i, s.childLanes = e.childLanes & ~n, t.memoizedState = Uf, r
    }
    return s = e.child, e = s.sibling, r = Br(s, {
        mode: "visible",
        children: r.children
    }), !(t.mode & 1) && (r.lanes = n), r.return = t, r.sibling = null, e !== null && (n = t.deletions, n === null ? (t.deletions = [e], t.flags |= 16) : n.push(e)), t.child = r, t.memoizedState = null, r
}

function Sh(e, t) {
    return t = au({mode: "visible", children: t}, e.mode, 0, null), t.return = e, e.child = t
}

function hl(e, t, n, r) {
    return r !== null && ch(r), Is(t, e.child, null, n), e = Sh(t, t.pendingProps.children), e.flags |= 2, t.memoizedState = null, e
}

function b_(e, t, n, r, o, s, i) {
    if (n) return t.flags & 256 ? (t.flags &= -257, r = Td(Error(G(422))), hl(e, t, i, r)) : t.memoizedState !== null ? (t.child = e.child, t.flags |= 128, null) : (s = r.fallback, o = t.mode, r = au({
        mode: "visible",
        children: r.children
    }, o, 0, null), s = wo(s, o, i, null), s.flags |= 2, r.return = t, s.return = t, r.sibling = s, t.child = r, t.mode & 1 && Is(t, e.child, null, i), t.child.memoizedState = Wf(i), t.memoizedState = Uf, s);
    if (!(t.mode & 1)) return hl(e, t, i, null);
    if (o.data === "$!") {
        if (r = o.nextSibling && o.nextSibling.dataset, r) var a = r.dgst;
        return r = a, s = Error(G(419)), r = Td(s, r, void 0), hl(e, t, i, r)
    }
    if (a = (i & e.childLanes) !== 0, gt || a) {
        if (r = We, r !== null) {
            switch (i & -i) {
                case 4:
                    o = 2;
                    break;
                case 16:
                    o = 8;
                    break;
                case 64:
                case 128:
                case 256:
                case 512:
                case 1024:
                case 2048:
                case 4096:
                case 8192:
                case 16384:
                case 32768:
                case 65536:
                case 131072:
                case 262144:
                case 524288:
                case 1048576:
                case 2097152:
                case 4194304:
                case 8388608:
                case 16777216:
                case 33554432:
                case 67108864:
                    o = 32;
                    break;
                case 536870912:
                    o = 268435456;
                    break;
                default:
                    o = 0
            }
            o = o & (r.suspendedLanes | i) ? 0 : o, o !== 0 && o !== s.retryLane && (s.retryLane = o, Jn(e, o), cn(r, e, o, -1))
        }
        return Ph(), r = Td(Error(G(421))), hl(e, t, i, r)
    }
    return o.data === "$?" ? (t.flags |= 128, t.child = e.child, t = I_.bind(null, e), o._reactRetry = t, null) : (e = s.treeContext, Pt = $r(o.nextSibling), Tt = t, Pe = !0, on = null, e !== null && (Ft[zt++] = Kn, Ft[zt++] = Yn, Ft[zt++] = ko, Kn = e.id, Yn = e.overflow, ko = t), t = Sh(t, r.children), t.flags |= 4096, t)
}

function Yg(e, t, n) {
    e.lanes |= t;
    var r = e.alternate;
    r !== null && (r.lanes |= t), Lf(e.return, t, n)
}

function Md(e, t, n, r, o) {
    var s = e.memoizedState;
    s === null ? e.memoizedState = {
        isBackwards: t,
        rendering: null,
        renderingStartTime: 0,
        last: r,
        tail: n,
        tailMode: o
    } : (s.isBackwards = t, s.rendering = null, s.renderingStartTime = 0, s.last = r, s.tail = n, s.tailMode = o)
}

function nw(e, t, n) {
    var r = t.pendingProps, o = r.revealOrder, s = r.tail;
    if (st(e, t, r.children, n), r = Me.current, r & 2) r = r & 1 | 2, t.flags |= 128; else {
        if (e !== null && e.flags & 128) e:for (e = t.child; e !== null;) {
            if (e.tag === 13) e.memoizedState !== null && Yg(e, n, t); else if (e.tag === 19) Yg(e, n, t); else if (e.child !== null) {
                e.child.return = e, e = e.child;
                continue
            }
            if (e === t) break e;
            for (; e.sibling === null;) {
                if (e.return === null || e.return === t) break e;
                e = e.return
            }
            e.sibling.return = e.return, e = e.sibling
        }
        r &= 1
    }
    if (Ce(Me, r), !(t.mode & 1)) t.memoizedState = null; else switch (o) {
        case"forwards":
            for (n = t.child, o = null; n !== null;) e = n.alternate, e !== null && bc(e) === null && (o = n), n = n.sibling;
            n = o, n === null ? (o = t.child, t.child = null) : (o = n.sibling, n.sibling = null), Md(t, !1, o, n, s);
            break;
        case"backwards":
            for (n = null, o = t.child, t.child = null; o !== null;) {
                if (e = o.alternate, e !== null && bc(e) === null) {
                    t.child = o;
                    break
                }
                e = o.sibling, o.sibling = n, n = o, o = e
            }
            Md(t, !0, n, null, s);
            break;
        case"together":
            Md(t, !1, null, null, void 0);
            break;
        default:
            t.memoizedState = null
    }
    return t.child
}

function Wl(e, t) {
    !(t.mode & 1) && e !== null && (e.alternate = null, t.alternate = null, t.flags |= 2)
}

function er(e, t, n) {
    if (e !== null && (t.dependencies = e.dependencies), jo |= t.lanes, !(n & t.childLanes)) return null;
    if (e !== null && t.child !== e.child) throw Error(G(153));
    if (t.child !== null) {
        for (e = t.child, n = Br(e, e.pendingProps), t.child = n, n.return = t; e.sibling !== null;) e = e.sibling, n = n.sibling = Br(e, e.pendingProps), n.return = t;
        n.sibling = null
    }
    return t.child
}

function C_(e, t, n) {
    switch (t.tag) {
        case 3:
            ew(t), As();
            break;
        case 5:
            jx(t);
            break;
        case 1:
            xt(t.type) && mc(t);
            break;
        case 4:
            mh(t, t.stateNode.containerInfo);
            break;
        case 10:
            var r = t.type._context, o = t.memoizedProps.value;
            Ce(yc, r._currentValue), r._currentValue = o;
            break;
        case 13:
            if (r = t.memoizedState, r !== null) return r.dehydrated !== null ? (Ce(Me, Me.current & 1), t.flags |= 128, null) : n & t.child.childLanes ? tw(e, t, n) : (Ce(Me, Me.current & 1), e = er(e, t, n), e !== null ? e.sibling : null);
            Ce(Me, Me.current & 1);
            break;
        case 19:
            if (r = (n & t.childLanes) !== 0, e.flags & 128) {
                if (r) return nw(e, t, n);
                t.flags |= 128
            }
            if (o = t.memoizedState, o !== null && (o.rendering = null, o.tail = null, o.lastEffect = null), Ce(Me, Me.current), r) break;
            return null;
        case 22:
        case 23:
            return t.lanes = 0, Zx(e, t, n)
    }
    return er(e, t, n)
}

var rw, Kf, ow, sw;
rw = function (e, t) {
    for (var n = t.child; n !== null;) {
        if (n.tag === 5 || n.tag === 6) e.appendChild(n.stateNode); else if (n.tag !== 4 && n.child !== null) {
            n.child.return = n, n = n.child;
            continue
        }
        if (n === t) break;
        for (; n.sibling === null;) {
            if (n.return === null || n.return === t) return;
            n = n.return
        }
        n.sibling.return = n.return, n = n.sibling
    }
};
Kf = function () {
};
ow = function (e, t, n, r) {
    var o = e.memoizedProps;
    if (o !== r) {
        e = t.stateNode, fo(Tn.current);
        var s = null;
        switch (n) {
            case"input":
                o = hf(e, o), r = hf(e, r), s = [];
                break;
            case"select":
                o = Ae({}, o, {value: void 0}), r = Ae({}, r, {value: void 0}), s = [];
                break;
            case"textarea":
                o = vf(e, o), r = vf(e, r), s = [];
                break;
            default:
                typeof o.onClick != "function" && typeof r.onClick == "function" && (e.onclick = pc)
        }
        xf(n, r);
        var i;
        n = null;
        for (u in o) if (!r.hasOwnProperty(u) && o.hasOwnProperty(u) && o[u] != null) if (u === "style") {
            var a = o[u];
            for (i in a) a.hasOwnProperty(i) && (n || (n = {}), n[i] = "")
        } else u !== "dangerouslySetInnerHTML" && u !== "children" && u !== "suppressContentEditableWarning" && u !== "suppressHydrationWarning" && u !== "autoFocus" && (zi.hasOwnProperty(u) ? s || (s = []) : (s = s || []).push(u, null));
        for (u in r) {
            var l = r[u];
            if (a = o != null ? o[u] : void 0, r.hasOwnProperty(u) && l !== a && (l != null || a != null)) if (u === "style") if (a) {
                for (i in a) !a.hasOwnProperty(i) || l && l.hasOwnProperty(i) || (n || (n = {}), n[i] = "");
                for (i in l) l.hasOwnProperty(i) && a[i] !== l[i] && (n || (n = {}), n[i] = l[i])
            } else n || (s || (s = []), s.push(u, n)), n = l; else u === "dangerouslySetInnerHTML" ? (l = l ? l.__html : void 0, a = a ? a.__html : void 0, l != null && a !== l && (s = s || []).push(u, l)) : u === "children" ? typeof l != "string" && typeof l != "number" || (s = s || []).push(u, "" + l) : u !== "suppressContentEditableWarning" && u !== "suppressHydrationWarning" && (zi.hasOwnProperty(u) ? (l != null && u === "onScroll" && ke("scroll", e), s || a === l || (s = [])) : (s = s || []).push(u, l))
        }
        n && (s = s || []).push("style", n);
        var u = s;
        (t.updateQueue = u) && (t.flags |= 4)
    }
};
sw = function (e, t, n, r) {
    n !== r && (t.flags |= 4)
};

function pi(e, t) {
    if (!Pe) switch (e.tailMode) {
        case"hidden":
            t = e.tail;
            for (var n = null; t !== null;) t.alternate !== null && (n = t), t = t.sibling;
            n === null ? e.tail = null : n.sibling = null;
            break;
        case"collapsed":
            n = e.tail;
            for (var r = null; n !== null;) n.alternate !== null && (r = n), n = n.sibling;
            r === null ? t || e.tail === null ? e.tail = null : e.tail.sibling = null : r.sibling = null
    }
}

function Ze(e) {
    var t = e.alternate !== null && e.alternate.child === e.child, n = 0, r = 0;
    if (t) for (var o = e.child; o !== null;) n |= o.lanes | o.childLanes, r |= o.subtreeFlags & 14680064, r |= o.flags & 14680064, o.return = e, o = o.sibling; else for (o = e.child; o !== null;) n |= o.lanes | o.childLanes, r |= o.subtreeFlags, r |= o.flags, o.return = e, o = o.sibling;
    return e.subtreeFlags |= r, e.childLanes = n, t
}

function S_(e, t, n) {
    var r = t.pendingProps;
    switch (lh(t), t.tag) {
        case 2:
        case 16:
        case 15:
        case 0:
        case 11:
        case 7:
        case 8:
        case 12:
        case 9:
        case 14:
            return Ze(t), null;
        case 1:
            return xt(t.type) && hc(), Ze(t), null;
        case 3:
            return r = t.stateNode, Os(), _e(yt), _e(tt), vh(), r.pendingContext && (r.context = r.pendingContext, r.pendingContext = null), (e === null || e.child === null) && (fl(t) ? t.flags |= 4 : e === null || e.memoizedState.isDehydrated && !(t.flags & 256) || (t.flags |= 1024, on !== null && (ep(on), on = null))), Kf(e, t), Ze(t), null;
        case 5:
            gh(t);
            var o = fo(Zi.current);
            if (n = t.type, e !== null && t.stateNode != null) ow(e, t, n, r, o), e.ref !== t.ref && (t.flags |= 512, t.flags |= 2097152); else {
                if (!r) {
                    if (t.stateNode === null) throw Error(G(166));
                    return Ze(t), null
                }
                if (e = fo(Tn.current), fl(t)) {
                    r = t.stateNode, n = t.type;
                    var s = t.memoizedProps;
                    switch (r[_n] = t, r[qi] = s, e = (t.mode & 1) !== 0, n) {
                        case"dialog":
                            ke("cancel", r), ke("close", r);
                            break;
                        case"iframe":
                        case"object":
                        case"embed":
                            ke("load", r);
                            break;
                        case"video":
                        case"audio":
                            for (o = 0; o < Si.length; o++) ke(Si[o], r);
                            break;
                        case"source":
                            ke("error", r);
                            break;
                        case"img":
                        case"image":
                        case"link":
                            ke("error", r), ke("load", r);
                            break;
                        case"details":
                            ke("toggle", r);
                            break;
                        case"input":
                            ng(r, s), ke("invalid", r);
                            break;
                        case"select":
                            r._wrapperState = {wasMultiple: !!s.multiple}, ke("invalid", r);
                            break;
                        case"textarea":
                            og(r, s), ke("invalid", r)
                    }
                    xf(n, s), o = null;
                    for (var i in s) if (s.hasOwnProperty(i)) {
                        var a = s[i];
                        i === "children" ? typeof a == "string" ? r.textContent !== a && (s.suppressHydrationWarning !== !0 && dl(r.textContent, a, e), o = ["children", a]) : typeof a == "number" && r.textContent !== "" + a && (s.suppressHydrationWarning !== !0 && dl(r.textContent, a, e), o = ["children", "" + a]) : zi.hasOwnProperty(i) && a != null && i === "onScroll" && ke("scroll", r)
                    }
                    switch (n) {
                        case"input":
                            rl(r), rg(r, s, !0);
                            break;
                        case"textarea":
                            rl(r), sg(r);
                            break;
                        case"select":
                        case"option":
                            break;
                        default:
                            typeof s.onClick == "function" && (r.onclick = pc)
                    }
                    r = o, t.updateQueue = r, r !== null && (t.flags |= 4)
                } else {
                    i = o.nodeType === 9 ? o : o.ownerDocument, e === "http://www.w3.org/1999/xhtml" && (e = Ay(n)), e === "http://www.w3.org/1999/xhtml" ? n === "script" ? (e = i.createElement("div"), e.innerHTML = "<script><\/script>", e = e.removeChild(e.firstChild)) : typeof r.is == "string" ? e = i.createElement(n, {is: r.is}) : (e = i.createElement(n), n === "select" && (i = e, r.multiple ? i.multiple = !0 : r.size && (i.size = r.size))) : e = i.createElementNS(e, n), e[_n] = t, e[qi] = r, rw(e, t, !1, !1), t.stateNode = e;
                    e:{
                        switch (i = wf(n, r), n) {
                            case"dialog":
                                ke("cancel", e), ke("close", e), o = r;
                                break;
                            case"iframe":
                            case"object":
                            case"embed":
                                ke("load", e), o = r;
                                break;
                            case"video":
                            case"audio":
                                for (o = 0; o < Si.length; o++) ke(Si[o], e);
                                o = r;
                                break;
                            case"source":
                                ke("error", e), o = r;
                                break;
                            case"img":
                            case"image":
                            case"link":
                                ke("error", e), ke("load", e), o = r;
                                break;
                            case"details":
                                ke("toggle", e), o = r;
                                break;
                            case"input":
                                ng(e, r), o = hf(e, r), ke("invalid", e);
                                break;
                            case"option":
                                o = r;
                                break;
                            case"select":
                                e._wrapperState = {wasMultiple: !!r.multiple}, o = Ae({}, r, {value: void 0}), ke("invalid", e);
                                break;
                            case"textarea":
                                og(e, r), o = vf(e, r), ke("invalid", e);
                                break;
                            default:
                                o = r
                        }
                        xf(n, o), a = o;
                        for (s in a) if (a.hasOwnProperty(s)) {
                            var l = a[s];
                            s === "style" ? Dy(e, l) : s === "dangerouslySetInnerHTML" ? (l = l ? l.__html : void 0, l != null && Iy(e, l)) : s === "children" ? typeof l == "string" ? (n !== "textarea" || l !== "") && Bi(e, l) : typeof l == "number" && Bi(e, "" + l) : s !== "suppressContentEditableWarning" && s !== "suppressHydrationWarning" && s !== "autoFocus" && (zi.hasOwnProperty(s) ? l != null && s === "onScroll" && ke("scroll", e) : l != null && Yp(e, s, l, i))
                        }
                        switch (n) {
                            case"input":
                                rl(e), rg(e, r, !1);
                                break;
                            case"textarea":
                                rl(e), sg(e);
                                break;
                            case"option":
                                r.value != null && e.setAttribute("value", "" + Hr(r.value));
                                break;
                            case"select":
                                e.multiple = !!r.multiple, s = r.value, s != null ? ds(e, !!r.multiple, s, !1) : r.defaultValue != null && ds(e, !!r.multiple, r.defaultValue, !0);
                                break;
                            default:
                                typeof o.onClick == "function" && (e.onclick = pc)
                        }
                        switch (n) {
                            case"button":
                            case"input":
                            case"select":
                            case"textarea":
                                r = !!r.autoFocus;
                                break e;
                            case"img":
                                r = !0;
                                break e;
                            default:
                                r = !1
                        }
                    }
                    r && (t.flags |= 4)
                }
                t.ref !== null && (t.flags |= 512, t.flags |= 2097152)
            }
            return Ze(t), null;
        case 6:
            if (e && t.stateNode != null) sw(e, t, e.memoizedProps, r); else {
                if (typeof r != "string" && t.stateNode === null) throw Error(G(166));
                if (n = fo(Zi.current), fo(Tn.current), fl(t)) {
                    if (r = t.stateNode, n = t.memoizedProps, r[_n] = t, (s = r.nodeValue !== n) && (e = Tt, e !== null)) switch (e.tag) {
                        case 3:
                            dl(r.nodeValue, n, (e.mode & 1) !== 0);
                            break;
                        case 5:
                            e.memoizedProps.suppressHydrationWarning !== !0 && dl(r.nodeValue, n, (e.mode & 1) !== 0)
                    }
                    s && (t.flags |= 4)
                } else r = (n.nodeType === 9 ? n : n.ownerDocument).createTextNode(r), r[_n] = t, t.stateNode = r
            }
            return Ze(t), null;
        case 13:
            if (_e(Me), r = t.memoizedState, e === null || e.memoizedState !== null && e.memoizedState.dehydrated !== null) {
                if (Pe && Pt !== null && t.mode & 1 && !(t.flags & 128)) Sx(), As(), t.flags |= 98560, s = !1; else if (s = fl(t), r !== null && r.dehydrated !== null) {
                    if (e === null) {
                        if (!s) throw Error(G(318));
                        if (s = t.memoizedState, s = s !== null ? s.dehydrated : null, !s) throw Error(G(317));
                        s[_n] = t
                    } else As(), !(t.flags & 128) && (t.memoizedState = null), t.flags |= 4;
                    Ze(t), s = !1
                } else on !== null && (ep(on), on = null), s = !0;
                if (!s) return t.flags & 65536 ? t : null
            }
            return t.flags & 128 ? (t.lanes = n, t) : (r = r !== null, r !== (e !== null && e.memoizedState !== null) && r && (t.child.flags |= 8192, t.mode & 1 && (e === null || Me.current & 1 ? He === 0 && (He = 3) : Ph())), t.updateQueue !== null && (t.flags |= 4), Ze(t), null);
        case 4:
            return Os(), Kf(e, t), e === null && Gi(t.stateNode.containerInfo), Ze(t), null;
        case 10:
            return fh(t.type._context), Ze(t), null;
        case 17:
            return xt(t.type) && hc(), Ze(t), null;
        case 19:
            if (_e(Me), s = t.memoizedState, s === null) return Ze(t), null;
            if (r = (t.flags & 128) !== 0, i = s.rendering, i === null) if (r) pi(s, !1); else {
                if (He !== 0 || e !== null && e.flags & 128) for (e = t.child; e !== null;) {
                    if (i = bc(e), i !== null) {
                        for (t.flags |= 128, pi(s, !1), r = i.updateQueue, r !== null && (t.updateQueue = r, t.flags |= 4), t.subtreeFlags = 0, r = n, n = t.child; n !== null;) s = n, e = r, s.flags &= 14680066, i = s.alternate, i === null ? (s.childLanes = 0, s.lanes = e, s.child = null, s.subtreeFlags = 0, s.memoizedProps = null, s.memoizedState = null, s.updateQueue = null, s.dependencies = null, s.stateNode = null) : (s.childLanes = i.childLanes, s.lanes = i.lanes, s.child = i.child, s.subtreeFlags = 0, s.deletions = null, s.memoizedProps = i.memoizedProps, s.memoizedState = i.memoizedState, s.updateQueue = i.updateQueue, s.type = i.type, e = i.dependencies, s.dependencies = e === null ? null : {
                            lanes: e.lanes,
                            firstContext: e.firstContext
                        }), n = n.sibling;
                        return Ce(Me, Me.current & 1 | 2), t.child
                    }
                    e = e.sibling
                }
                s.tail !== null && Le() > $s && (t.flags |= 128, r = !0, pi(s, !1), t.lanes = 4194304)
            } else {
                if (!r) if (e = bc(i), e !== null) {
                    if (t.flags |= 128, r = !0, n = e.updateQueue, n !== null && (t.updateQueue = n, t.flags |= 4), pi(s, !0), s.tail === null && s.tailMode === "hidden" && !i.alternate && !Pe) return Ze(t), null
                } else 2 * Le() - s.renderingStartTime > $s && n !== 1073741824 && (t.flags |= 128, r = !0, pi(s, !1), t.lanes = 4194304);
                s.isBackwards ? (i.sibling = t.child, t.child = i) : (n = s.last, n !== null ? n.sibling = i : t.child = i, s.last = i)
            }
            return s.tail !== null ? (t = s.tail, s.rendering = t, s.tail = t.sibling, s.renderingStartTime = Le(), t.sibling = null, n = Me.current, Ce(Me, r ? n & 1 | 2 : n & 1), t) : (Ze(t), null);
        case 22:
        case 23:
            return jh(), r = t.memoizedState !== null, e !== null && e.memoizedState !== null !== r && (t.flags |= 8192), r && t.mode & 1 ? kt & 1073741824 && (Ze(t), t.subtreeFlags & 6 && (t.flags |= 8192)) : Ze(t), null;
        case 24:
            return null;
        case 25:
            return null
    }
    throw Error(G(156, t.tag))
}

function N_(e, t) {
    switch (lh(t), t.tag) {
        case 1:
            return xt(t.type) && hc(), e = t.flags, e & 65536 ? (t.flags = e & -65537 | 128, t) : null;
        case 3:
            return Os(), _e(yt), _e(tt), vh(), e = t.flags, e & 65536 && !(e & 128) ? (t.flags = e & -65537 | 128, t) : null;
        case 5:
            return gh(t), null;
        case 13:
            if (_e(Me), e = t.memoizedState, e !== null && e.dehydrated !== null) {
                if (t.alternate === null) throw Error(G(340));
                As()
            }
            return e = t.flags, e & 65536 ? (t.flags = e & -65537 | 128, t) : null;
        case 19:
            return _e(Me), null;
        case 4:
            return Os(), null;
        case 10:
            return fh(t.type._context), null;
        case 22:
        case 23:
            return jh(), null;
        case 24:
            return null;
        default:
            return null
    }
}

var ml = !1, et = !1, E_ = typeof WeakSet == "function" ? WeakSet : Set, ne = null;

function as(e, t) {
    var n = e.ref;
    if (n !== null) if (typeof n == "function") try {
        n(null)
    } catch (r) {
        Oe(e, t, r)
    } else n.current = null
}

function Yf(e, t, n) {
    try {
        n()
    } catch (r) {
        Oe(e, t, r)
    }
}

var Gg = !1;

function k_(e, t) {
    if (Tf = uc, e = ux(), ih(e)) {
        if ("selectionStart" in e) var n = {start: e.selectionStart, end: e.selectionEnd}; else e:{
            n = (n = e.ownerDocument) && n.defaultView || window;
            var r = n.getSelection && n.getSelection();
            if (r && r.rangeCount !== 0) {
                n = r.anchorNode;
                var o = r.anchorOffset, s = r.focusNode;
                r = r.focusOffset;
                try {
                    n.nodeType, s.nodeType
                } catch {
                    n = null;
                    break e
                }
                var i = 0, a = -1, l = -1, u = 0, d = 0, f = e, p = null;
                t:for (; ;) {
                    for (var h; f !== n || o !== 0 && f.nodeType !== 3 || (a = i + o), f !== s || r !== 0 && f.nodeType !== 3 || (l = i + r), f.nodeType === 3 && (i += f.nodeValue.length), (h = f.firstChild) !== null;) p = f, f = h;
                    for (; ;) {
                        if (f === e) break t;
                        if (p === n && ++u === o && (a = i), p === s && ++d === r && (l = i), (h = f.nextSibling) !== null) break;
                        f = p, p = f.parentNode
                    }
                    f = h
                }
                n = a === -1 || l === -1 ? null : {start: a, end: l}
            } else n = null
        }
        n = n || {start: 0, end: 0}
    } else n = null;
    for (Mf = {
        focusedElem: e,
        selectionRange: n
    }, uc = !1, ne = t; ne !== null;) if (t = ne, e = t.child, (t.subtreeFlags & 1028) !== 0 && e !== null) e.return = t, ne = e; else for (; ne !== null;) {
        t = ne;
        try {
            var x = t.alternate;
            if (t.flags & 1024) switch (t.tag) {
                case 0:
                case 11:
                case 15:
                    break;
                case 1:
                    if (x !== null) {
                        var m = x.memoizedProps, b = x.memoizedState, y = t.stateNode,
                            w = y.getSnapshotBeforeUpdate(t.elementType === t.type ? m : Zt(t.type, m), b);
                        y.__reactInternalSnapshotBeforeUpdate = w
                    }
                    break;
                case 3:
                    var v = t.stateNode.containerInfo;
                    v.nodeType === 1 ? v.textContent = "" : v.nodeType === 9 && v.documentElement && v.removeChild(v.documentElement);
                    break;
                case 5:
                case 6:
                case 4:
                case 17:
                    break;
                default:
                    throw Error(G(163))
            }
        } catch (C) {
            Oe(t, t.return, C)
        }
        if (e = t.sibling, e !== null) {
            e.return = t.return, ne = e;
            break
        }
        ne = t.return
    }
    return x = Gg, Gg = !1, x
}

function Ii(e, t, n) {
    var r = t.updateQueue;
    if (r = r !== null ? r.lastEffect : null, r !== null) {
        var o = r = r.next;
        do {
            if ((o.tag & e) === e) {
                var s = o.destroy;
                o.destroy = void 0, s !== void 0 && Yf(t, n, s)
            }
            o = o.next
        } while (o !== r)
    }
}

function su(e, t) {
    if (t = t.updateQueue, t = t !== null ? t.lastEffect : null, t !== null) {
        var n = t = t.next;
        do {
            if ((n.tag & e) === e) {
                var r = n.create;
                n.destroy = r()
            }
            n = n.next
        } while (n !== t)
    }
}

function Gf(e) {
    var t = e.ref;
    if (t !== null) {
        var n = e.stateNode;
        switch (e.tag) {
            case 5:
                e = n;
                break;
            default:
                e = n
        }
        typeof t == "function" ? t(e) : t.current = e
    }
}

function iw(e) {
    var t = e.alternate;
    t !== null && (e.alternate = null, iw(t)), e.child = null, e.deletions = null, e.sibling = null, e.tag === 5 && (t = e.stateNode, t !== null && (delete t[_n], delete t[qi], delete t[If], delete t[l_], delete t[c_])), e.stateNode = null, e.return = null, e.dependencies = null, e.memoizedProps = null, e.memoizedState = null, e.pendingProps = null, e.stateNode = null, e.updateQueue = null
}

function aw(e) {
    return e.tag === 5 || e.tag === 3 || e.tag === 4
}

function Xg(e) {
    e:for (; ;) {
        for (; e.sibling === null;) {
            if (e.return === null || aw(e.return)) return null;
            e = e.return
        }
        for (e.sibling.return = e.return, e = e.sibling; e.tag !== 5 && e.tag !== 6 && e.tag !== 18;) {
            if (e.flags & 2 || e.child === null || e.tag === 4) continue e;
            e.child.return = e, e = e.child
        }
        if (!(e.flags & 2)) return e.stateNode
    }
}

function Xf(e, t, n) {
    var r = e.tag;
    if (r === 5 || r === 6) e = e.stateNode, t ? n.nodeType === 8 ? n.parentNode.insertBefore(e, t) : n.insertBefore(e, t) : (n.nodeType === 8 ? (t = n.parentNode, t.insertBefore(e, n)) : (t = n, t.appendChild(e)), n = n._reactRootContainer, n != null || t.onclick !== null || (t.onclick = pc)); else if (r !== 4 && (e = e.child, e !== null)) for (Xf(e, t, n), e = e.sibling; e !== null;) Xf(e, t, n), e = e.sibling
}

function qf(e, t, n) {
    var r = e.tag;
    if (r === 5 || r === 6) e = e.stateNode, t ? n.insertBefore(e, t) : n.appendChild(e); else if (r !== 4 && (e = e.child, e !== null)) for (qf(e, t, n), e = e.sibling; e !== null;) qf(e, t, n), e = e.sibling
}

var Ke = null, nn = !1;

function hr(e, t, n) {
    for (n = n.child; n !== null;) lw(e, t, n), n = n.sibling
}

function lw(e, t, n) {
    if (Pn && typeof Pn.onCommitFiberUnmount == "function") try {
        Pn.onCommitFiberUnmount(Qc, n)
    } catch {
    }
    switch (n.tag) {
        case 5:
            et || as(n, t);
        case 6:
            var r = Ke, o = nn;
            Ke = null, hr(e, t, n), Ke = r, nn = o, Ke !== null && (nn ? (e = Ke, n = n.stateNode, e.nodeType === 8 ? e.parentNode.removeChild(n) : e.removeChild(n)) : Ke.removeChild(n.stateNode));
            break;
        case 18:
            Ke !== null && (nn ? (e = Ke, n = n.stateNode, e.nodeType === 8 ? Nd(e.parentNode, n) : e.nodeType === 1 && Nd(e, n), Wi(e)) : Nd(Ke, n.stateNode));
            break;
        case 4:
            r = Ke, o = nn, Ke = n.stateNode.containerInfo, nn = !0, hr(e, t, n), Ke = r, nn = o;
            break;
        case 0:
        case 11:
        case 14:
        case 15:
            if (!et && (r = n.updateQueue, r !== null && (r = r.lastEffect, r !== null))) {
                o = r = r.next;
                do {
                    var s = o, i = s.destroy;
                    s = s.tag, i !== void 0 && (s & 2 || s & 4) && Yf(n, t, i), o = o.next
                } while (o !== r)
            }
            hr(e, t, n);
            break;
        case 1:
            if (!et && (as(n, t), r = n.stateNode, typeof r.componentWillUnmount == "function")) try {
                r.props = n.memoizedProps, r.state = n.memoizedState, r.componentWillUnmount()
            } catch (a) {
                Oe(n, t, a)
            }
            hr(e, t, n);
            break;
        case 21:
            hr(e, t, n);
            break;
        case 22:
            n.mode & 1 ? (et = (r = et) || n.memoizedState !== null, hr(e, t, n), et = r) : hr(e, t, n);
            break;
        default:
            hr(e, t, n)
    }
}

function qg(e) {
    var t = e.updateQueue;
    if (t !== null) {
        e.updateQueue = null;
        var n = e.stateNode;
        n === null && (n = e.stateNode = new E_), t.forEach(function (r) {
            var o = O_.bind(null, e, r);
            n.has(r) || (n.add(r), r.then(o, o))
        })
    }
}

function Xt(e, t) {
    var n = t.deletions;
    if (n !== null) for (var r = 0; r < n.length; r++) {
        var o = n[r];
        try {
            var s = e, i = t, a = i;
            e:for (; a !== null;) {
                switch (a.tag) {
                    case 5:
                        Ke = a.stateNode, nn = !1;
                        break e;
                    case 3:
                        Ke = a.stateNode.containerInfo, nn = !0;
                        break e;
                    case 4:
                        Ke = a.stateNode.containerInfo, nn = !0;
                        break e
                }
                a = a.return
            }
            if (Ke === null) throw Error(G(160));
            lw(s, i, o), Ke = null, nn = !1;
            var l = o.alternate;
            l !== null && (l.return = null), o.return = null
        } catch (u) {
            Oe(o, t, u)
        }
    }
    if (t.subtreeFlags & 12854) for (t = t.child; t !== null;) cw(t, e), t = t.sibling
}

function cw(e, t) {
    var n = e.alternate, r = e.flags;
    switch (e.tag) {
        case 0:
        case 11:
        case 14:
        case 15:
            if (Xt(t, e), Cn(e), r & 4) {
                try {
                    Ii(3, e, e.return), su(3, e)
                } catch (m) {
                    Oe(e, e.return, m)
                }
                try {
                    Ii(5, e, e.return)
                } catch (m) {
                    Oe(e, e.return, m)
                }
            }
            break;
        case 1:
            Xt(t, e), Cn(e), r & 512 && n !== null && as(n, n.return);
            break;
        case 5:
            if (Xt(t, e), Cn(e), r & 512 && n !== null && as(n, n.return), e.flags & 32) {
                var o = e.stateNode;
                try {
                    Bi(o, "")
                } catch (m) {
                    Oe(e, e.return, m)
                }
            }
            if (r & 4 && (o = e.stateNode, o != null)) {
                var s = e.memoizedProps, i = n !== null ? n.memoizedProps : s, a = e.type, l = e.updateQueue;
                if (e.updateQueue = null, l !== null) try {
                    a === "input" && s.type === "radio" && s.name != null && My(o, s), wf(a, i);
                    var u = wf(a, s);
                    for (i = 0; i < l.length; i += 2) {
                        var d = l[i], f = l[i + 1];
                        d === "style" ? Dy(o, f) : d === "dangerouslySetInnerHTML" ? Iy(o, f) : d === "children" ? Bi(o, f) : Yp(o, d, f, u)
                    }
                    switch (a) {
                        case"input":
                            mf(o, s);
                            break;
                        case"textarea":
                            Ry(o, s);
                            break;
                        case"select":
                            var p = o._wrapperState.wasMultiple;
                            o._wrapperState.wasMultiple = !!s.multiple;
                            var h = s.value;
                            h != null ? ds(o, !!s.multiple, h, !1) : p !== !!s.multiple && (s.defaultValue != null ? ds(o, !!s.multiple, s.defaultValue, !0) : ds(o, !!s.multiple, s.multiple ? [] : "", !1))
                    }
                    o[qi] = s
                } catch (m) {
                    Oe(e, e.return, m)
                }
            }
            break;
        case 6:
            if (Xt(t, e), Cn(e), r & 4) {
                if (e.stateNode === null) throw Error(G(162));
                o = e.stateNode, s = e.memoizedProps;
                try {
                    o.nodeValue = s
                } catch (m) {
                    Oe(e, e.return, m)
                }
            }
            break;
        case 3:
            if (Xt(t, e), Cn(e), r & 4 && n !== null && n.memoizedState.isDehydrated) try {
                Wi(t.containerInfo)
            } catch (m) {
                Oe(e, e.return, m)
            }
            break;
        case 4:
            Xt(t, e), Cn(e);
            break;
        case 13:
            Xt(t, e), Cn(e), o = e.child, o.flags & 8192 && (s = o.memoizedState !== null, o.stateNode.isHidden = s, !s || o.alternate !== null && o.alternate.memoizedState !== null || (kh = Le())), r & 4 && qg(e);
            break;
        case 22:
            if (d = n !== null && n.memoizedState !== null, e.mode & 1 ? (et = (u = et) || d, Xt(t, e), et = u) : Xt(t, e), Cn(e), r & 8192) {
                if (u = e.memoizedState !== null, (e.stateNode.isHidden = u) && !d && e.mode & 1) for (ne = e, d = e.child; d !== null;) {
                    for (f = ne = d; ne !== null;) {
                        switch (p = ne, h = p.child, p.tag) {
                            case 0:
                            case 11:
                            case 14:
                            case 15:
                                Ii(4, p, p.return);
                                break;
                            case 1:
                                as(p, p.return);
                                var x = p.stateNode;
                                if (typeof x.componentWillUnmount == "function") {
                                    r = p, n = p.return;
                                    try {
                                        t = r, x.props = t.memoizedProps, x.state = t.memoizedState, x.componentWillUnmount()
                                    } catch (m) {
                                        Oe(r, n, m)
                                    }
                                }
                                break;
                            case 5:
                                as(p, p.return);
                                break;
                            case 22:
                                if (p.memoizedState !== null) {
                                    Zg(f);
                                    continue
                                }
                        }
                        h !== null ? (h.return = p, ne = h) : Zg(f)
                    }
                    d = d.sibling
                }
                e:for (d = null, f = e; ;) {
                    if (f.tag === 5) {
                        if (d === null) {
                            d = f;
                            try {
                                o = f.stateNode, u ? (s = o.style, typeof s.setProperty == "function" ? s.setProperty("display", "none", "important") : s.display = "none") : (a = f.stateNode, l = f.memoizedProps.style, i = l != null && l.hasOwnProperty("display") ? l.display : null, a.style.display = Oy("display", i))
                            } catch (m) {
                                Oe(e, e.return, m)
                            }
                        }
                    } else if (f.tag === 6) {
                        if (d === null) try {
                            f.stateNode.nodeValue = u ? "" : f.memoizedProps
                        } catch (m) {
                            Oe(e, e.return, m)
                        }
                    } else if ((f.tag !== 22 && f.tag !== 23 || f.memoizedState === null || f === e) && f.child !== null) {
                        f.child.return = f, f = f.child;
                        continue
                    }
                    if (f === e) break e;
                    for (; f.sibling === null;) {
                        if (f.return === null || f.return === e) break e;
                        d === f && (d = null), f = f.return
                    }
                    d === f && (d = null), f.sibling.return = f.return, f = f.sibling
                }
            }
            break;
        case 19:
            Xt(t, e), Cn(e), r & 4 && qg(e);
            break;
        case 21:
            break;
        default:
            Xt(t, e), Cn(e)
    }
}

function Cn(e) {
    var t = e.flags;
    if (t & 2) {
        try {
            e:{
                for (var n = e.return; n !== null;) {
                    if (aw(n)) {
                        var r = n;
                        break e
                    }
                    n = n.return
                }
                throw Error(G(160))
            }
            switch (r.tag) {
                case 5:
                    var o = r.stateNode;
                    r.flags & 32 && (Bi(o, ""), r.flags &= -33);
                    var s = Xg(e);
                    qf(e, s, o);
                    break;
                case 3:
                case 4:
                    var i = r.stateNode.containerInfo, a = Xg(e);
                    Xf(e, a, i);
                    break;
                default:
                    throw Error(G(161))
            }
        } catch (l) {
            Oe(e, e.return, l)
        }
        e.flags &= -3
    }
    t & 4096 && (e.flags &= -4097)
}

function __(e, t, n) {
    ne = e, uw(e)
}

function uw(e, t, n) {
    for (var r = (e.mode & 1) !== 0; ne !== null;) {
        var o = ne, s = o.child;
        if (o.tag === 22 && r) {
            var i = o.memoizedState !== null || ml;
            if (!i) {
                var a = o.alternate, l = a !== null && a.memoizedState !== null || et;
                a = ml;
                var u = et;
                if (ml = i, (et = l) && !u) for (ne = o; ne !== null;) i = ne, l = i.child, i.tag === 22 && i.memoizedState !== null ? Jg(o) : l !== null ? (l.return = i, ne = l) : Jg(o);
                for (; s !== null;) ne = s, uw(s), s = s.sibling;
                ne = o, ml = a, et = u
            }
            Qg(e)
        } else o.subtreeFlags & 8772 && s !== null ? (s.return = o, ne = s) : Qg(e)
    }
}

function Qg(e) {
    for (; ne !== null;) {
        var t = ne;
        if (t.flags & 8772) {
            var n = t.alternate;
            try {
                if (t.flags & 8772) switch (t.tag) {
                    case 0:
                    case 11:
                    case 15:
                        et || su(5, t);
                        break;
                    case 1:
                        var r = t.stateNode;
                        if (t.flags & 4 && !et) if (n === null) r.componentDidMount(); else {
                            var o = t.elementType === t.type ? n.memoizedProps : Zt(t.type, n.memoizedProps);
                            r.componentDidUpdate(o, n.memoizedState, r.__reactInternalSnapshotBeforeUpdate)
                        }
                        var s = t.updateQueue;
                        s !== null && Og(t, s, r);
                        break;
                    case 3:
                        var i = t.updateQueue;
                        if (i !== null) {
                            if (n = null, t.child !== null) switch (t.child.tag) {
                                case 5:
                                    n = t.child.stateNode;
                                    break;
                                case 1:
                                    n = t.child.stateNode
                            }
                            Og(t, i, n)
                        }
                        break;
                    case 5:
                        var a = t.stateNode;
                        if (n === null && t.flags & 4) {
                            n = a;
                            var l = t.memoizedProps;
                            switch (t.type) {
                                case"button":
                                case"input":
                                case"select":
                                case"textarea":
                                    l.autoFocus && n.focus();
                                    break;
                                case"img":
                                    l.src && (n.src = l.src)
                            }
                        }
                        break;
                    case 6:
                        break;
                    case 4:
                        break;
                    case 12:
                        break;
                    case 13:
                        if (t.memoizedState === null) {
                            var u = t.alternate;
                            if (u !== null) {
                                var d = u.memoizedState;
                                if (d !== null) {
                                    var f = d.dehydrated;
                                    f !== null && Wi(f)
                                }
                            }
                        }
                        break;
                    case 19:
                    case 17:
                    case 21:
                    case 22:
                    case 23:
                    case 25:
                        break;
                    default:
                        throw Error(G(163))
                }
                et || t.flags & 512 && Gf(t)
            } catch (p) {
                Oe(t, t.return, p)
            }
        }
        if (t === e) {
            ne = null;
            break
        }
        if (n = t.sibling, n !== null) {
            n.return = t.return, ne = n;
            break
        }
        ne = t.return
    }
}

function Zg(e) {
    for (; ne !== null;) {
        var t = ne;
        if (t === e) {
            ne = null;
            break
        }
        var n = t.sibling;
        if (n !== null) {
            n.return = t.return, ne = n;
            break
        }
        ne = t.return
    }
}

function Jg(e) {
    for (; ne !== null;) {
        var t = ne;
        try {
            switch (t.tag) {
                case 0:
                case 11:
                case 15:
                    var n = t.return;
                    try {
                        su(4, t)
                    } catch (l) {
                        Oe(t, n, l)
                    }
                    break;
                case 1:
                    var r = t.stateNode;
                    if (typeof r.componentDidMount == "function") {
                        var o = t.return;
                        try {
                            r.componentDidMount()
                        } catch (l) {
                            Oe(t, o, l)
                        }
                    }
                    var s = t.return;
                    try {
                        Gf(t)
                    } catch (l) {
                        Oe(t, s, l)
                    }
                    break;
                case 5:
                    var i = t.return;
                    try {
                        Gf(t)
                    } catch (l) {
                        Oe(t, i, l)
                    }
            }
        } catch (l) {
            Oe(t, t.return, l)
        }
        if (t === e) {
            ne = null;
            break
        }
        var a = t.sibling;
        if (a !== null) {
            a.return = t.return, ne = a;
            break
        }
        ne = t.return
    }
}

var j_ = Math.ceil, Nc = or.ReactCurrentDispatcher, Nh = or.ReactCurrentOwner, Vt = or.ReactCurrentBatchConfig, he = 0,
    We = null, Fe = null, Ye = 0, kt = 0, ls = Zr(0), He = 0, na = null, jo = 0, iu = 0, Eh = 0, Oi = null, mt = null,
    kh = 0, $s = 1 / 0, Hn = null, Ec = !1, Qf = null, Fr = null, gl = !1, Ar = null, kc = 0, Di = 0, Zf = null,
    Kl = -1, Yl = 0;

function lt() {
    return he & 6 ? Le() : Kl !== -1 ? Kl : Kl = Le()
}

function zr(e) {
    return e.mode & 1 ? he & 2 && Ye !== 0 ? Ye & -Ye : d_.transition !== null ? (Yl === 0 && (Yl = Gy()), Yl) : (e = xe, e !== 0 || (e = window.event, e = e === void 0 ? 16 : tx(e.type)), e) : 1
}

function cn(e, t, n, r) {
    if (50 < Di) throw Di = 0, Zf = null, Error(G(185));
    Sa(e, n, r), (!(he & 2) || e !== We) && (e === We && (!(he & 2) && (iu |= n), He === 4 && Er(e, Ye)), wt(e, r), n === 1 && he === 0 && !(t.mode & 1) && ($s = Le() + 500, nu && Jr()))
}

function wt(e, t) {
    var n = e.callbackNode;
    dk(e, t);
    var r = cc(e, e === We ? Ye : 0);
    if (r === 0) n !== null && lg(n), e.callbackNode = null, e.callbackPriority = 0; else if (t = r & -r, e.callbackPriority !== t) {
        if (n != null && lg(n), t === 1) e.tag === 0 ? u_(ev.bind(null, e)) : wx(ev.bind(null, e)), i_(function () {
            !(he & 6) && Jr()
        }), n = null; else {
            switch (Xy(r)) {
                case 1:
                    n = Zp;
                    break;
                case 4:
                    n = Ky;
                    break;
                case 16:
                    n = lc;
                    break;
                case 536870912:
                    n = Yy;
                    break;
                default:
                    n = lc
            }
            n = yw(n, dw.bind(null, e))
        }
        e.callbackPriority = t, e.callbackNode = n
    }
}

function dw(e, t) {
    if (Kl = -1, Yl = 0, he & 6) throw Error(G(327));
    var n = e.callbackNode;
    if (gs() && e.callbackNode !== n) return null;
    var r = cc(e, e === We ? Ye : 0);
    if (r === 0) return null;
    if (r & 30 || r & e.expiredLanes || t) t = _c(e, r); else {
        t = r;
        var o = he;
        he |= 2;
        var s = pw();
        (We !== e || Ye !== t) && (Hn = null, $s = Le() + 500, xo(e, t));
        do try {
            M_();
            break
        } catch (a) {
            fw(e, a)
        } while (!0);
        dh(), Nc.current = s, he = o, Fe !== null ? t = 0 : (We = null, Ye = 0, t = He)
    }
    if (t !== 0) {
        if (t === 2 && (o = Ef(e), o !== 0 && (r = o, t = Jf(e, o))), t === 1) throw n = na, xo(e, 0), Er(e, r), wt(e, Le()), n;
        if (t === 6) Er(e, r); else {
            if (o = e.current.alternate, !(r & 30) && !P_(o) && (t = _c(e, r), t === 2 && (s = Ef(e), s !== 0 && (r = s, t = Jf(e, s))), t === 1)) throw n = na, xo(e, 0), Er(e, r), wt(e, Le()), n;
            switch (e.finishedWork = o, e.finishedLanes = r, t) {
                case 0:
                case 1:
                    throw Error(G(345));
                case 2:
                    ao(e, mt, Hn);
                    break;
                case 3:
                    if (Er(e, r), (r & 130023424) === r && (t = kh + 500 - Le(), 10 < t)) {
                        if (cc(e, 0) !== 0) break;
                        if (o = e.suspendedLanes, (o & r) !== r) {
                            lt(), e.pingedLanes |= e.suspendedLanes & o;
                            break
                        }
                        e.timeoutHandle = Af(ao.bind(null, e, mt, Hn), t);
                        break
                    }
                    ao(e, mt, Hn);
                    break;
                case 4:
                    if (Er(e, r), (r & 4194240) === r) break;
                    for (t = e.eventTimes, o = -1; 0 < r;) {
                        var i = 31 - ln(r);
                        s = 1 << i, i = t[i], i > o && (o = i), r &= ~s
                    }
                    if (r = o, r = Le() - r, r = (120 > r ? 120 : 480 > r ? 480 : 1080 > r ? 1080 : 1920 > r ? 1920 : 3e3 > r ? 3e3 : 4320 > r ? 4320 : 1960 * j_(r / 1960)) - r, 10 < r) {
                        e.timeoutHandle = Af(ao.bind(null, e, mt, Hn), r);
                        break
                    }
                    ao(e, mt, Hn);
                    break;
                case 5:
                    ao(e, mt, Hn);
                    break;
                default:
                    throw Error(G(329))
            }
        }
    }
    return wt(e, Le()), e.callbackNode === n ? dw.bind(null, e) : null
}

function Jf(e, t) {
    var n = Oi;
    return e.current.memoizedState.isDehydrated && (xo(e, t).flags |= 256), e = _c(e, t), e !== 2 && (t = mt, mt = n, t !== null && ep(t)), e
}

function ep(e) {
    mt === null ? mt = e : mt.push.apply(mt, e)
}

function P_(e) {
    for (var t = e; ;) {
        if (t.flags & 16384) {
            var n = t.updateQueue;
            if (n !== null && (n = n.stores, n !== null)) for (var r = 0; r < n.length; r++) {
                var o = n[r], s = o.getSnapshot;
                o = o.value;
                try {
                    if (!fn(s(), o)) return !1
                } catch {
                    return !1
                }
            }
        }
        if (n = t.child, t.subtreeFlags & 16384 && n !== null) n.return = t, t = n; else {
            if (t === e) break;
            for (; t.sibling === null;) {
                if (t.return === null || t.return === e) return !0;
                t = t.return
            }
            t.sibling.return = t.return, t = t.sibling
        }
    }
    return !0
}

function Er(e, t) {
    for (t &= ~Eh, t &= ~iu, e.suspendedLanes |= t, e.pingedLanes &= ~t, e = e.expirationTimes; 0 < t;) {
        var n = 31 - ln(t), r = 1 << n;
        e[n] = -1, t &= ~r
    }
}

function ev(e) {
    if (he & 6) throw Error(G(327));
    gs();
    var t = cc(e, 0);
    if (!(t & 1)) return wt(e, Le()), null;
    var n = _c(e, t);
    if (e.tag !== 0 && n === 2) {
        var r = Ef(e);
        r !== 0 && (t = r, n = Jf(e, r))
    }
    if (n === 1) throw n = na, xo(e, 0), Er(e, t), wt(e, Le()), n;
    if (n === 6) throw Error(G(345));
    return e.finishedWork = e.current.alternate, e.finishedLanes = t, ao(e, mt, Hn), wt(e, Le()), null
}

function _h(e, t) {
    var n = he;
    he |= 1;
    try {
        return e(t)
    } finally {
        he = n, he === 0 && ($s = Le() + 500, nu && Jr())
    }
}

function Po(e) {
    Ar !== null && Ar.tag === 0 && !(he & 6) && gs();
    var t = he;
    he |= 1;
    var n = Vt.transition, r = xe;
    try {
        if (Vt.transition = null, xe = 1, e) return e()
    } finally {
        xe = r, Vt.transition = n, he = t, !(he & 6) && Jr()
    }
}

function jh() {
    kt = ls.current, _e(ls)
}

function xo(e, t) {
    e.finishedWork = null, e.finishedLanes = 0;
    var n = e.timeoutHandle;
    if (n !== -1 && (e.timeoutHandle = -1, s_(n)), Fe !== null) for (n = Fe.return; n !== null;) {
        var r = n;
        switch (lh(r), r.tag) {
            case 1:
                r = r.type.childContextTypes, r != null && hc();
                break;
            case 3:
                Os(), _e(yt), _e(tt), vh();
                break;
            case 5:
                gh(r);
                break;
            case 4:
                Os();
                break;
            case 13:
                _e(Me);
                break;
            case 19:
                _e(Me);
                break;
            case 10:
                fh(r.type._context);
                break;
            case 22:
            case 23:
                jh()
        }
        n = n.return
    }
    if (We = e, Fe = e = Br(e.current, null), Ye = kt = t, He = 0, na = null, Eh = iu = jo = 0, mt = Oi = null, uo !== null) {
        for (t = 0; t < uo.length; t++) if (n = uo[t], r = n.interleaved, r !== null) {
            n.interleaved = null;
            var o = r.next, s = n.pending;
            if (s !== null) {
                var i = s.next;
                s.next = o, r.next = i
            }
            n.pending = r
        }
        uo = null
    }
    return e
}

function fw(e, t) {
    do {
        var n = Fe;
        try {
            if (dh(), Hl.current = Sc, Cc) {
                for (var r = Re.memoizedState; r !== null;) {
                    var o = r.queue;
                    o !== null && (o.pending = null), r = r.next
                }
                Cc = !1
            }
            if (_o = 0, Ue = Ve = Re = null, Ai = !1, Ji = 0, Nh.current = null, n === null || n.return === null) {
                He = 1, na = t, Fe = null;
                break
            }
            e:{
                var s = e, i = n.return, a = n, l = t;
                if (t = Ye, a.flags |= 32768, l !== null && typeof l == "object" && typeof l.then == "function") {
                    var u = l, d = a, f = d.tag;
                    if (!(d.mode & 1) && (f === 0 || f === 11 || f === 15)) {
                        var p = d.alternate;
                        p ? (d.updateQueue = p.updateQueue, d.memoizedState = p.memoizedState, d.lanes = p.lanes) : (d.updateQueue = null, d.memoizedState = null)
                    }
                    var h = Bg(i);
                    if (h !== null) {
                        h.flags &= -257, Vg(h, i, a, s, t), h.mode & 1 && zg(s, u, t), t = h, l = u;
                        var x = t.updateQueue;
                        if (x === null) {
                            var m = new Set;
                            m.add(l), t.updateQueue = m
                        } else x.add(l);
                        break e
                    } else {
                        if (!(t & 1)) {
                            zg(s, u, t), Ph();
                            break e
                        }
                        l = Error(G(426))
                    }
                } else if (Pe && a.mode & 1) {
                    var b = Bg(i);
                    if (b !== null) {
                        !(b.flags & 65536) && (b.flags |= 256), Vg(b, i, a, s, t), ch(Ds(l, a));
                        break e
                    }
                }
                s = l = Ds(l, a), He !== 4 && (He = 2), Oi === null ? Oi = [s] : Oi.push(s), s = i;
                do {
                    switch (s.tag) {
                        case 3:
                            s.flags |= 65536, t &= -t, s.lanes |= t;
                            var y = Xx(s, l, t);
                            Ig(s, y);
                            break e;
                        case 1:
                            a = l;
                            var w = s.type, v = s.stateNode;
                            if (!(s.flags & 128) && (typeof w.getDerivedStateFromError == "function" || v !== null && typeof v.componentDidCatch == "function" && (Fr === null || !Fr.has(v)))) {
                                s.flags |= 65536, t &= -t, s.lanes |= t;
                                var C = qx(s, a, t);
                                Ig(s, C);
                                break e
                            }
                    }
                    s = s.return
                } while (s !== null)
            }
            mw(n)
        } catch (S) {
            t = S, Fe === n && n !== null && (Fe = n = n.return);
            continue
        }
        break
    } while (!0)
}

function pw() {
    var e = Nc.current;
    return Nc.current = Sc, e === null ? Sc : e
}

function Ph() {
    (He === 0 || He === 3 || He === 2) && (He = 4), We === null || !(jo & 268435455) && !(iu & 268435455) || Er(We, Ye)
}

function _c(e, t) {
    var n = he;
    he |= 2;
    var r = pw();
    (We !== e || Ye !== t) && (Hn = null, xo(e, t));
    do try {
        T_();
        break
    } catch (o) {
        fw(e, o)
    } while (!0);
    if (dh(), he = n, Nc.current = r, Fe !== null) throw Error(G(261));
    return We = null, Ye = 0, He
}

function T_() {
    for (; Fe !== null;) hw(Fe)
}

function M_() {
    for (; Fe !== null && !nk();) hw(Fe)
}

function hw(e) {
    var t = vw(e.alternate, e, kt);
    e.memoizedProps = e.pendingProps, t === null ? mw(e) : Fe = t, Nh.current = null
}

function mw(e) {
    var t = e;
    do {
        var n = t.alternate;
        if (e = t.return, t.flags & 32768) {
            if (n = N_(n, t), n !== null) {
                n.flags &= 32767, Fe = n;
                return
            }
            if (e !== null) e.flags |= 32768, e.subtreeFlags = 0, e.deletions = null; else {
                He = 6, Fe = null;
                return
            }
        } else if (n = S_(n, t, kt), n !== null) {
            Fe = n;
            return
        }
        if (t = t.sibling, t !== null) {
            Fe = t;
            return
        }
        Fe = t = e
    } while (t !== null);
    He === 0 && (He = 5)
}

function ao(e, t, n) {
    var r = xe, o = Vt.transition;
    try {
        Vt.transition = null, xe = 1, R_(e, t, n, r)
    } finally {
        Vt.transition = o, xe = r
    }
    return null
}

function R_(e, t, n, r) {
    do gs(); while (Ar !== null);
    if (he & 6) throw Error(G(327));
    n = e.finishedWork;
    var o = e.finishedLanes;
    if (n === null) return null;
    if (e.finishedWork = null, e.finishedLanes = 0, n === e.current) throw Error(G(177));
    e.callbackNode = null, e.callbackPriority = 0;
    var s = n.lanes | n.childLanes;
    if (fk(e, s), e === We && (Fe = We = null, Ye = 0), !(n.subtreeFlags & 2064) && !(n.flags & 2064) || gl || (gl = !0, yw(lc, function () {
        return gs(), null
    })), s = (n.flags & 15990) !== 0, n.subtreeFlags & 15990 || s) {
        s = Vt.transition, Vt.transition = null;
        var i = xe;
        xe = 1;
        var a = he;
        he |= 4, Nh.current = null, k_(e, n), cw(n, e), Zk(Mf), uc = !!Tf, Mf = Tf = null, e.current = n, __(n), rk(), he = a, xe = i, Vt.transition = s
    } else e.current = n;
    if (gl && (gl = !1, Ar = e, kc = o), s = e.pendingLanes, s === 0 && (Fr = null), ik(n.stateNode), wt(e, Le()), t !== null) for (r = e.onRecoverableError, n = 0; n < t.length; n++) o = t[n], r(o.value, {
        componentStack: o.stack,
        digest: o.digest
    });
    if (Ec) throw Ec = !1, e = Qf, Qf = null, e;
    return kc & 1 && e.tag !== 0 && gs(), s = e.pendingLanes, s & 1 ? e === Zf ? Di++ : (Di = 0, Zf = e) : Di = 0, Jr(), null
}

function gs() {
    if (Ar !== null) {
        var e = Xy(kc), t = Vt.transition, n = xe;
        try {
            if (Vt.transition = null, xe = 16 > e ? 16 : e, Ar === null) var r = !1; else {
                if (e = Ar, Ar = null, kc = 0, he & 6) throw Error(G(331));
                var o = he;
                for (he |= 4, ne = e.current; ne !== null;) {
                    var s = ne, i = s.child;
                    if (ne.flags & 16) {
                        var a = s.deletions;
                        if (a !== null) {
                            for (var l = 0; l < a.length; l++) {
                                var u = a[l];
                                for (ne = u; ne !== null;) {
                                    var d = ne;
                                    switch (d.tag) {
                                        case 0:
                                        case 11:
                                        case 15:
                                            Ii(8, d, s)
                                    }
                                    var f = d.child;
                                    if (f !== null) f.return = d, ne = f; else for (; ne !== null;) {
                                        d = ne;
                                        var p = d.sibling, h = d.return;
                                        if (iw(d), d === u) {
                                            ne = null;
                                            break
                                        }
                                        if (p !== null) {
                                            p.return = h, ne = p;
                                            break
                                        }
                                        ne = h
                                    }
                                }
                            }
                            var x = s.alternate;
                            if (x !== null) {
                                var m = x.child;
                                if (m !== null) {
                                    x.child = null;
                                    do {
                                        var b = m.sibling;
                                        m.sibling = null, m = b
                                    } while (m !== null)
                                }
                            }
                            ne = s
                        }
                    }
                    if (s.subtreeFlags & 2064 && i !== null) i.return = s, ne = i; else e:for (; ne !== null;) {
                        if (s = ne, s.flags & 2048) switch (s.tag) {
                            case 0:
                            case 11:
                            case 15:
                                Ii(9, s, s.return)
                        }
                        var y = s.sibling;
                        if (y !== null) {
                            y.return = s.return, ne = y;
                            break e
                        }
                        ne = s.return
                    }
                }
                var w = e.current;
                for (ne = w; ne !== null;) {
                    i = ne;
                    var v = i.child;
                    if (i.subtreeFlags & 2064 && v !== null) v.return = i, ne = v; else e:for (i = w; ne !== null;) {
                        if (a = ne, a.flags & 2048) try {
                            switch (a.tag) {
                                case 0:
                                case 11:
                                case 15:
                                    su(9, a)
                            }
                        } catch (S) {
                            Oe(a, a.return, S)
                        }
                        if (a === i) {
                            ne = null;
                            break e
                        }
                        var C = a.sibling;
                        if (C !== null) {
                            C.return = a.return, ne = C;
                            break e
                        }
                        ne = a.return
                    }
                }
                if (he = o, Jr(), Pn && typeof Pn.onPostCommitFiberRoot == "function") try {
                    Pn.onPostCommitFiberRoot(Qc, e)
                } catch {
                }
                r = !0
            }
            return r
        } finally {
            xe = n, Vt.transition = t
        }
    }
    return !1
}

function tv(e, t, n) {
    t = Ds(n, t), t = Xx(e, t, 1), e = Lr(e, t, 1), t = lt(), e !== null && (Sa(e, 1, t), wt(e, t))
}

function Oe(e, t, n) {
    if (e.tag === 3) tv(e, e, n); else for (; t !== null;) {
        if (t.tag === 3) {
            tv(t, e, n);
            break
        } else if (t.tag === 1) {
            var r = t.stateNode;
            if (typeof t.type.getDerivedStateFromError == "function" || typeof r.componentDidCatch == "function" && (Fr === null || !Fr.has(r))) {
                e = Ds(n, e), e = qx(t, e, 1), t = Lr(t, e, 1), e = lt(), t !== null && (Sa(t, 1, e), wt(t, e));
                break
            }
        }
        t = t.return
    }
}

function A_(e, t, n) {
    var r = e.pingCache;
    r !== null && r.delete(t), t = lt(), e.pingedLanes |= e.suspendedLanes & n, We === e && (Ye & n) === n && (He === 4 || He === 3 && (Ye & 130023424) === Ye && 500 > Le() - kh ? xo(e, 0) : Eh |= n), wt(e, t)
}

function gw(e, t) {
    t === 0 && (e.mode & 1 ? (t = il, il <<= 1, !(il & 130023424) && (il = 4194304)) : t = 1);
    var n = lt();
    e = Jn(e, t), e !== null && (Sa(e, t, n), wt(e, n))
}

function I_(e) {
    var t = e.memoizedState, n = 0;
    t !== null && (n = t.retryLane), gw(e, n)
}

function O_(e, t) {
    var n = 0;
    switch (e.tag) {
        case 13:
            var r = e.stateNode, o = e.memoizedState;
            o !== null && (n = o.retryLane);
            break;
        case 19:
            r = e.stateNode;
            break;
        default:
            throw Error(G(314))
    }
    r !== null && r.delete(t), gw(e, n)
}

var vw;
vw = function (e, t, n) {
    if (e !== null) if (e.memoizedProps !== t.pendingProps || yt.current) gt = !0; else {
        if (!(e.lanes & n) && !(t.flags & 128)) return gt = !1, C_(e, t, n);
        gt = !!(e.flags & 131072)
    } else gt = !1, Pe && t.flags & 1048576 && bx(t, vc, t.index);
    switch (t.lanes = 0, t.tag) {
        case 2:
            var r = t.type;
            Wl(e, t), e = t.pendingProps;
            var o = Rs(t, tt.current);
            ms(t, n), o = xh(null, t, r, e, o, n);
            var s = wh();
            return t.flags |= 1, typeof o == "object" && o !== null && typeof o.render == "function" && o.$$typeof === void 0 ? (t.tag = 1, t.memoizedState = null, t.updateQueue = null, xt(r) ? (s = !0, mc(t)) : s = !1, t.memoizedState = o.state !== null && o.state !== void 0 ? o.state : null, hh(t), o.updater = ou, t.stateNode = o, o._reactInternals = t, zf(t, r, e, n), t = Hf(null, t, r, !0, s, n)) : (t.tag = 0, Pe && s && ah(t), st(null, t, o, n), t = t.child), t;
        case 16:
            r = t.elementType;
            e:{
                switch (Wl(e, t), e = t.pendingProps, o = r._init, r = o(r._payload), t.type = r, o = t.tag = $_(r), e = Zt(r, e), o) {
                    case 0:
                        t = Vf(null, t, r, e, n);
                        break e;
                    case 1:
                        t = Wg(null, t, r, e, n);
                        break e;
                    case 11:
                        t = Hg(null, t, r, e, n);
                        break e;
                    case 14:
                        t = Ug(null, t, r, Zt(r.type, e), n);
                        break e
                }
                throw Error(G(306, r, ""))
            }
            return t;
        case 0:
            return r = t.type, o = t.pendingProps, o = t.elementType === r ? o : Zt(r, o), Vf(e, t, r, o, n);
        case 1:
            return r = t.type, o = t.pendingProps, o = t.elementType === r ? o : Zt(r, o), Wg(e, t, r, o, n);
        case 3:
            e:{
                if (ew(t), e === null) throw Error(G(387));
                r = t.pendingProps, s = t.memoizedState, o = s.element, _x(e, t), wc(t, r, null, n);
                var i = t.memoizedState;
                if (r = i.element, s.isDehydrated) if (s = {
                    element: r,
                    isDehydrated: !1,
                    cache: i.cache,
                    pendingSuspenseBoundaries: i.pendingSuspenseBoundaries,
                    transitions: i.transitions
                }, t.updateQueue.baseState = s, t.memoizedState = s, t.flags & 256) {
                    o = Ds(Error(G(423)), t), t = Kg(e, t, r, n, o);
                    break e
                } else if (r !== o) {
                    o = Ds(Error(G(424)), t), t = Kg(e, t, r, n, o);
                    break e
                } else for (Pt = $r(t.stateNode.containerInfo.firstChild), Tt = t, Pe = !0, on = null, n = Ex(t, null, r, n), t.child = n; n;) n.flags = n.flags & -3 | 4096, n = n.sibling; else {
                    if (As(), r === o) {
                        t = er(e, t, n);
                        break e
                    }
                    st(e, t, r, n)
                }
                t = t.child
            }
            return t;
        case 5:
            return jx(t), e === null && $f(t), r = t.type, o = t.pendingProps, s = e !== null ? e.memoizedProps : null, i = o.children, Rf(r, o) ? i = null : s !== null && Rf(r, s) && (t.flags |= 32), Jx(e, t), st(e, t, i, n), t.child;
        case 6:
            return e === null && $f(t), null;
        case 13:
            return tw(e, t, n);
        case 4:
            return mh(t, t.stateNode.containerInfo), r = t.pendingProps, e === null ? t.child = Is(t, null, r, n) : st(e, t, r, n), t.child;
        case 11:
            return r = t.type, o = t.pendingProps, o = t.elementType === r ? o : Zt(r, o), Hg(e, t, r, o, n);
        case 7:
            return st(e, t, t.pendingProps, n), t.child;
        case 8:
            return st(e, t, t.pendingProps.children, n), t.child;
        case 12:
            return st(e, t, t.pendingProps.children, n), t.child;
        case 10:
            e:{
                if (r = t.type._context, o = t.pendingProps, s = t.memoizedProps, i = o.value, Ce(yc, r._currentValue), r._currentValue = i, s !== null) if (fn(s.value, i)) {
                    if (s.children === o.children && !yt.current) {
                        t = er(e, t, n);
                        break e
                    }
                } else for (s = t.child, s !== null && (s.return = t); s !== null;) {
                    var a = s.dependencies;
                    if (a !== null) {
                        i = s.child;
                        for (var l = a.firstContext; l !== null;) {
                            if (l.context === r) {
                                if (s.tag === 1) {
                                    l = Xn(-1, n & -n), l.tag = 2;
                                    var u = s.updateQueue;
                                    if (u !== null) {
                                        u = u.shared;
                                        var d = u.pending;
                                        d === null ? l.next = l : (l.next = d.next, d.next = l), u.pending = l
                                    }
                                }
                                s.lanes |= n, l = s.alternate, l !== null && (l.lanes |= n), Lf(s.return, n, t), a.lanes |= n;
                                break
                            }
                            l = l.next
                        }
                    } else if (s.tag === 10) i = s.type === t.type ? null : s.child; else if (s.tag === 18) {
                        if (i = s.return, i === null) throw Error(G(341));
                        i.lanes |= n, a = i.alternate, a !== null && (a.lanes |= n), Lf(i, n, t), i = s.sibling
                    } else i = s.child;
                    if (i !== null) i.return = s; else for (i = s; i !== null;) {
                        if (i === t) {
                            i = null;
                            break
                        }
                        if (s = i.sibling, s !== null) {
                            s.return = i.return, i = s;
                            break
                        }
                        i = i.return
                    }
                    s = i
                }
                st(e, t, o.children, n), t = t.child
            }
            return t;
        case 9:
            return o = t.type, r = t.pendingProps.children, ms(t, n), o = Kt(o), r = r(o), t.flags |= 1, st(e, t, r, n), t.child;
        case 14:
            return r = t.type, o = Zt(r, t.pendingProps), o = Zt(r.type, o), Ug(e, t, r, o, n);
        case 15:
            return Qx(e, t, t.type, t.pendingProps, n);
        case 17:
            return r = t.type, o = t.pendingProps, o = t.elementType === r ? o : Zt(r, o), Wl(e, t), t.tag = 1, xt(r) ? (e = !0, mc(t)) : e = !1, ms(t, n), Gx(t, r, o), zf(t, r, o, n), Hf(null, t, r, !0, e, n);
        case 19:
            return nw(e, t, n);
        case 22:
            return Zx(e, t, n)
    }
    throw Error(G(156, t.tag))
};

function yw(e, t) {
    return Wy(e, t)
}

function D_(e, t, n, r) {
    this.tag = e, this.key = n, this.sibling = this.child = this.return = this.stateNode = this.type = this.elementType = null, this.index = 0, this.ref = null, this.pendingProps = t, this.dependencies = this.memoizedState = this.updateQueue = this.memoizedProps = null, this.mode = r, this.subtreeFlags = this.flags = 0, this.deletions = null, this.childLanes = this.lanes = 0, this.alternate = null
}

function Bt(e, t, n, r) {
    return new D_(e, t, n, r)
}

function Th(e) {
    return e = e.prototype, !(!e || !e.isReactComponent)
}

function $_(e) {
    if (typeof e == "function") return Th(e) ? 1 : 0;
    if (e != null) {
        if (e = e.$$typeof, e === Xp) return 11;
        if (e === qp) return 14
    }
    return 2
}

function Br(e, t) {
    var n = e.alternate;
    return n === null ? (n = Bt(e.tag, t, e.key, e.mode), n.elementType = e.elementType, n.type = e.type, n.stateNode = e.stateNode, n.alternate = e, e.alternate = n) : (n.pendingProps = t, n.type = e.type, n.flags = 0, n.subtreeFlags = 0, n.deletions = null), n.flags = e.flags & 14680064, n.childLanes = e.childLanes, n.lanes = e.lanes, n.child = e.child, n.memoizedProps = e.memoizedProps, n.memoizedState = e.memoizedState, n.updateQueue = e.updateQueue, t = e.dependencies, n.dependencies = t === null ? null : {
        lanes: t.lanes,
        firstContext: t.firstContext
    }, n.sibling = e.sibling, n.index = e.index, n.ref = e.ref, n
}

function Gl(e, t, n, r, o, s) {
    var i = 2;
    if (r = e, typeof e == "function") Th(e) && (i = 1); else if (typeof e == "string") i = 5; else e:switch (e) {
        case Zo:
            return wo(n.children, o, s, t);
        case Gp:
            i = 8, o |= 8;
            break;
        case uf:
            return e = Bt(12, n, t, o | 2), e.elementType = uf, e.lanes = s, e;
        case df:
            return e = Bt(13, n, t, o), e.elementType = df, e.lanes = s, e;
        case ff:
            return e = Bt(19, n, t, o), e.elementType = ff, e.lanes = s, e;
        case jy:
            return au(n, o, s, t);
        default:
            if (typeof e == "object" && e !== null) switch (e.$$typeof) {
                case ky:
                    i = 10;
                    break e;
                case _y:
                    i = 9;
                    break e;
                case Xp:
                    i = 11;
                    break e;
                case qp:
                    i = 14;
                    break e;
                case br:
                    i = 16, r = null;
                    break e
            }
            throw Error(G(130, e == null ? e : typeof e, ""))
    }
    return t = Bt(i, n, t, o), t.elementType = e, t.type = r, t.lanes = s, t
}

function wo(e, t, n, r) {
    return e = Bt(7, e, r, t), e.lanes = n, e
}

function au(e, t, n, r) {
    return e = Bt(22, e, r, t), e.elementType = jy, e.lanes = n, e.stateNode = {isHidden: !1}, e
}

function Rd(e, t, n) {
    return e = Bt(6, e, null, t), e.lanes = n, e
}

function Ad(e, t, n) {
    return t = Bt(4, e.children !== null ? e.children : [], e.key, t), t.lanes = n, t.stateNode = {
        containerInfo: e.containerInfo,
        pendingChildren: null,
        implementation: e.implementation
    }, t
}

function L_(e, t, n, r, o) {
    this.tag = t, this.containerInfo = e, this.finishedWork = this.pingCache = this.current = this.pendingChildren = null, this.timeoutHandle = -1, this.callbackNode = this.pendingContext = this.context = null, this.callbackPriority = 0, this.eventTimes = pd(0), this.expirationTimes = pd(-1), this.entangledLanes = this.finishedLanes = this.mutableReadLanes = this.expiredLanes = this.pingedLanes = this.suspendedLanes = this.pendingLanes = 0, this.entanglements = pd(0), this.identifierPrefix = r, this.onRecoverableError = o, this.mutableSourceEagerHydrationData = null
}

function Mh(e, t, n, r, o, s, i, a, l) {
    return e = new L_(e, t, n, a, l), t === 1 ? (t = 1, s === !0 && (t |= 8)) : t = 0, s = Bt(3, null, null, t), e.current = s, s.stateNode = e, s.memoizedState = {
        element: r,
        isDehydrated: n,
        cache: null,
        transitions: null,
        pendingSuspenseBoundaries: null
    }, hh(s), e
}

function F_(e, t, n) {
    var r = 3 < arguments.length && arguments[3] !== void 0 ? arguments[3] : null;
    return {$$typeof: Qo, key: r == null ? null : "" + r, children: e, containerInfo: t, implementation: n}
}

function xw(e) {
    if (!e) return Ur;
    e = e._reactInternals;
    e:{
        if (Lo(e) !== e || e.tag !== 1) throw Error(G(170));
        var t = e;
        do {
            switch (t.tag) {
                case 3:
                    t = t.stateNode.context;
                    break e;
                case 1:
                    if (xt(t.type)) {
                        t = t.stateNode.__reactInternalMemoizedMergedChildContext;
                        break e
                    }
            }
            t = t.return
        } while (t !== null);
        throw Error(G(171))
    }
    if (e.tag === 1) {
        var n = e.type;
        if (xt(n)) return xx(e, n, t)
    }
    return t
}

function ww(e, t, n, r, o, s, i, a, l) {
    return e = Mh(n, r, !0, e, o, s, i, a, l), e.context = xw(null), n = e.current, r = lt(), o = zr(n), s = Xn(r, o), s.callback = t ?? null, Lr(n, s, o), e.current.lanes = o, Sa(e, o, r), wt(e, r), e
}

function lu(e, t, n, r) {
    var o = t.current, s = lt(), i = zr(o);
    return n = xw(n), t.context === null ? t.context = n : t.pendingContext = n, t = Xn(s, i), t.payload = {element: e}, r = r === void 0 ? null : r, r !== null && (t.callback = r), e = Lr(o, t, i), e !== null && (cn(e, o, i, s), Vl(e, o, i)), i
}

function jc(e) {
    if (e = e.current, !e.child) return null;
    switch (e.child.tag) {
        case 5:
            return e.child.stateNode;
        default:
            return e.child.stateNode
    }
}

function nv(e, t) {
    if (e = e.memoizedState, e !== null && e.dehydrated !== null) {
        var n = e.retryLane;
        e.retryLane = n !== 0 && n < t ? n : t
    }
}

function Rh(e, t) {
    nv(e, t), (e = e.alternate) && nv(e, t)
}

function z_() {
    return null
}

var bw = typeof reportError == "function" ? reportError : function (e) {
    console.error(e)
};

function Ah(e) {
    this._internalRoot = e
}

cu.prototype.render = Ah.prototype.render = function (e) {
    var t = this._internalRoot;
    if (t === null) throw Error(G(409));
    lu(e, t, null, null)
};
cu.prototype.unmount = Ah.prototype.unmount = function () {
    var e = this._internalRoot;
    if (e !== null) {
        this._internalRoot = null;
        var t = e.containerInfo;
        Po(function () {
            lu(null, e, null, null)
        }), t[Zn] = null
    }
};

function cu(e) {
    this._internalRoot = e
}

cu.prototype.unstable_scheduleHydration = function (e) {
    if (e) {
        var t = Zy();
        e = {blockedOn: null, target: e, priority: t};
        for (var n = 0; n < Nr.length && t !== 0 && t < Nr[n].priority; n++) ;
        Nr.splice(n, 0, e), n === 0 && ex(e)
    }
};

function Ih(e) {
    return !(!e || e.nodeType !== 1 && e.nodeType !== 9 && e.nodeType !== 11)
}

function uu(e) {
    return !(!e || e.nodeType !== 1 && e.nodeType !== 9 && e.nodeType !== 11 && (e.nodeType !== 8 || e.nodeValue !== " react-mount-point-unstable "))
}

function rv() {
}

function B_(e, t, n, r, o) {
    if (o) {
        if (typeof r == "function") {
            var s = r;
            r = function () {
                var u = jc(i);
                s.call(u)
            }
        }
        var i = ww(t, r, e, 0, null, !1, !1, "", rv);
        return e._reactRootContainer = i, e[Zn] = i.current, Gi(e.nodeType === 8 ? e.parentNode : e), Po(), i
    }
    for (; o = e.lastChild;) e.removeChild(o);
    if (typeof r == "function") {
        var a = r;
        r = function () {
            var u = jc(l);
            a.call(u)
        }
    }
    var l = Mh(e, 0, !1, null, null, !1, !1, "", rv);
    return e._reactRootContainer = l, e[Zn] = l.current, Gi(e.nodeType === 8 ? e.parentNode : e), Po(function () {
        lu(t, l, n, r)
    }), l
}

function du(e, t, n, r, o) {
    var s = n._reactRootContainer;
    if (s) {
        var i = s;
        if (typeof o == "function") {
            var a = o;
            o = function () {
                var l = jc(i);
                a.call(l)
            }
        }
        lu(t, i, e, o)
    } else i = B_(n, t, e, o, r);
    return jc(i)
}

qy = function (e) {
    switch (e.tag) {
        case 3:
            var t = e.stateNode;
            if (t.current.memoizedState.isDehydrated) {
                var n = Ci(t.pendingLanes);
                n !== 0 && (Jp(t, n | 1), wt(t, Le()), !(he & 6) && ($s = Le() + 500, Jr()))
            }
            break;
        case 13:
            Po(function () {
                var r = Jn(e, 1);
                if (r !== null) {
                    var o = lt();
                    cn(r, e, 1, o)
                }
            }), Rh(e, 1)
    }
};
eh = function (e) {
    if (e.tag === 13) {
        var t = Jn(e, 134217728);
        if (t !== null) {
            var n = lt();
            cn(t, e, 134217728, n)
        }
        Rh(e, 134217728)
    }
};
Qy = function (e) {
    if (e.tag === 13) {
        var t = zr(e), n = Jn(e, t);
        if (n !== null) {
            var r = lt();
            cn(n, e, t, r)
        }
        Rh(e, t)
    }
};
Zy = function () {
    return xe
};
Jy = function (e, t) {
    var n = xe;
    try {
        return xe = e, t()
    } finally {
        xe = n
    }
};
Cf = function (e, t, n) {
    switch (t) {
        case"input":
            if (mf(e, n), t = n.name, n.type === "radio" && t != null) {
                for (n = e; n.parentNode;) n = n.parentNode;
                for (n = n.querySelectorAll("input[name=" + JSON.stringify("" + t) + '][type="radio"]'), t = 0; t < n.length; t++) {
                    var r = n[t];
                    if (r !== e && r.form === e.form) {
                        var o = tu(r);
                        if (!o) throw Error(G(90));
                        Ty(r), mf(r, o)
                    }
                }
            }
            break;
        case"textarea":
            Ry(e, n);
            break;
        case"select":
            t = n.value, t != null && ds(e, !!n.multiple, t, !1)
    }
};
Fy = _h;
zy = Po;
var V_ = {usingClientEntryPoint: !1, Events: [Ea, ns, tu, $y, Ly, _h]},
    hi = {findFiberByHostInstance: co, bundleType: 0, version: "18.3.1", rendererPackageName: "react-dom"}, H_ = {
        bundleType: hi.bundleType,
        version: hi.version,
        rendererPackageName: hi.rendererPackageName,
        rendererConfig: hi.rendererConfig,
        overrideHookState: null,
        overrideHookStateDeletePath: null,
        overrideHookStateRenamePath: null,
        overrideProps: null,
        overridePropsDeletePath: null,
        overridePropsRenamePath: null,
        setErrorHandler: null,
        setSuspenseHandler: null,
        scheduleUpdate: null,
        currentDispatcherRef: or.ReactCurrentDispatcher,
        findHostInstanceByFiber: function (e) {
            return e = Hy(e), e === null ? null : e.stateNode
        },
        findFiberByHostInstance: hi.findFiberByHostInstance || z_,
        findHostInstancesForRefresh: null,
        scheduleRefresh: null,
        scheduleRoot: null,
        setRefreshHandler: null,
        getCurrentFiber: null,
        reconcilerVersion: "18.3.1-next-f1338f8080-20240426"
    };
if (typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ < "u") {
    var vl = __REACT_DEVTOOLS_GLOBAL_HOOK__;
    if (!vl.isDisabled && vl.supportsFiber) try {
        Qc = vl.inject(H_), Pn = vl
    } catch {
    }
}
It.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED = V_;
It.createPortal = function (e, t) {
    var n = 2 < arguments.length && arguments[2] !== void 0 ? arguments[2] : null;
    if (!Ih(t)) throw Error(G(200));
    return F_(e, t, null, n)
};
It.createRoot = function (e, t) {
    if (!Ih(e)) throw Error(G(299));
    var n = !1, r = "", o = bw;
    return t != null && (t.unstable_strictMode === !0 && (n = !0), t.identifierPrefix !== void 0 && (r = t.identifierPrefix), t.onRecoverableError !== void 0 && (o = t.onRecoverableError)), t = Mh(e, 1, !1, null, null, n, !1, r, o), e[Zn] = t.current, Gi(e.nodeType === 8 ? e.parentNode : e), new Ah(t)
};
It.findDOMNode = function (e) {
    if (e == null) return null;
    if (e.nodeType === 1) return e;
    var t = e._reactInternals;
    if (t === void 0) throw typeof e.render == "function" ? Error(G(188)) : (e = Object.keys(e).join(","), Error(G(268, e)));
    return e = Hy(t), e = e === null ? null : e.stateNode, e
};
It.flushSync = function (e) {
    return Po(e)
};
It.hydrate = function (e, t, n) {
    if (!uu(t)) throw Error(G(200));
    return du(null, e, t, !0, n)
};
It.hydrateRoot = function (e, t, n) {
    if (!Ih(e)) throw Error(G(405));
    var r = n != null && n.hydratedSources || null, o = !1, s = "", i = bw;
    if (n != null && (n.unstable_strictMode === !0 && (o = !0), n.identifierPrefix !== void 0 && (s = n.identifierPrefix), n.onRecoverableError !== void 0 && (i = n.onRecoverableError)), t = ww(t, null, e, 1, n ?? null, o, !1, s, i), e[Zn] = t.current, Gi(e), r) for (e = 0; e < r.length; e++) n = r[e], o = n._getVersion, o = o(n._source), t.mutableSourceEagerHydrationData == null ? t.mutableSourceEagerHydrationData = [n, o] : t.mutableSourceEagerHydrationData.push(n, o);
    return new cu(t)
};
It.render = function (e, t, n) {
    if (!uu(t)) throw Error(G(200));
    return du(null, e, t, !1, n)
};
It.unmountComponentAtNode = function (e) {
    if (!uu(e)) throw Error(G(40));
    return e._reactRootContainer ? (Po(function () {
        du(null, null, e, !1, function () {
            e._reactRootContainer = null, e[Zn] = null
        })
    }), !0) : !1
};
It.unstable_batchedUpdates = _h;
It.unstable_renderSubtreeIntoContainer = function (e, t, n, r) {
    if (!uu(n)) throw Error(G(200));
    if (e == null || e._reactInternals === void 0) throw Error(G(38));
    return du(e, t, n, !1, r)
};
It.version = "18.3.1-next-f1338f8080-20240426";

function Cw() {
    if (!(typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ > "u" || typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE != "function")) try {
        __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE(Cw)
    } catch (e) {
        console.error(e)
    }
}

Cw(), Cy.exports = It;
var Fo = Cy.exports;
const Sw = zp(Fo);
var Nw, ov = Fo;
Nw = ov.createRoot, ov.hydrateRoot;
const U_ = 1, W_ = 1e6;
let Id = 0;

function K_() {
    return Id = (Id + 1) % Number.MAX_SAFE_INTEGER, Id.toString()
}

const Od = new Map, sv = e => {
    if (Od.has(e)) return;
    const t = setTimeout(() => {
        Od.delete(e), $i({type: "REMOVE_TOAST", toastId: e})
    }, W_);
    Od.set(e, t)
}, Y_ = (e, t) => {
    switch (t.type) {
        case"ADD_TOAST":
            return {...e, toasts: [t.toast, ...e.toasts].slice(0, U_)};
        case"UPDATE_TOAST":
            return {...e, toasts: e.toasts.map(n => n.id === t.toast.id ? {...n, ...t.toast} : n)};
        case"DISMISS_TOAST": {
            const {toastId: n} = t;
            return n ? sv(n) : e.toasts.forEach(r => {
                sv(r.id)
            }), {...e, toasts: e.toasts.map(r => r.id === n || n === void 0 ? {...r, open: !1} : r)}
        }
        case"REMOVE_TOAST":
            return t.toastId === void 0 ? {...e, toasts: []} : {...e, toasts: e.toasts.filter(n => n.id !== t.toastId)}
    }
}, Xl = [];
let ql = {toasts: []};

function $i(e) {
    ql = Y_(ql, e), Xl.forEach(t => {
        t(ql)
    })
}

function G_({...e}) {
    const t = K_(), n = o => $i({type: "UPDATE_TOAST", toast: {...o, id: t}}),
        r = () => $i({type: "DISMISS_TOAST", toastId: t});
    return $i({
        type: "ADD_TOAST", toast: {
            ...e, id: t, open: !0, onOpenChange: o => {
                o || r()
            }
        }
    }), {id: t, dismiss: r, update: n}
}

function Oh() {
    const [e, t] = g.useState(ql);
    return g.useEffect(() => (Xl.push(t), () => {
        const n = Xl.indexOf(t);
        n > -1 && Xl.splice(n, 1)
    }), [e]), {...e, toast: G_, dismiss: n => $i({type: "DISMISS_TOAST", toastId: n})}
}

function J(e, t, {checkForDefaultPrevented: n = !0} = {}) {
    return function (o) {
        if (e == null || e(o), n === !1 || !o.defaultPrevented) return t == null ? void 0 : t(o)
    }
}

function iv(e, t) {
    if (typeof e == "function") return e(t);
    e != null && (e.current = t)
}

function Wr(...e) {
    return t => {
        let n = !1;
        const r = e.map(o => {
            const s = iv(o, t);
            return !n && typeof s == "function" && (n = !0), s
        });
        if (n) return () => {
            for (let o = 0; o < r.length; o++) {
                const s = r[o];
                typeof s == "function" ? s() : iv(e[o], null)
            }
        }
    }
}

function ge(...e) {
    return g.useCallback(Wr(...e), e)
}

function X_(e, t) {
    const n = g.createContext(t), r = s => {
        const {children: i, ...a} = s, l = g.useMemo(() => a, Object.values(a));
        return c.jsx(n.Provider, {value: l, children: i})
    };
    r.displayName = e + "Provider";

    function o(s) {
        const i = g.useContext(n);
        if (i) return i;
        if (t !== void 0) return t;
        throw new Error(`\`${s}\` must be used within \`${e}\``)
    }

    return [r, o]
}

function mn(e, t = []) {
    let n = [];

    function r(s, i) {
        const a = g.createContext(i), l = n.length;
        n = [...n, i];
        const u = f => {
            var y;
            const {scope: p, children: h, ...x} = f, m = ((y = p == null ? void 0 : p[e]) == null ? void 0 : y[l]) || a,
                b = g.useMemo(() => x, Object.values(x));
            return c.jsx(m.Provider, {value: b, children: h})
        };
        u.displayName = s + "Provider";

        function d(f, p) {
            var m;
            const h = ((m = p == null ? void 0 : p[e]) == null ? void 0 : m[l]) || a, x = g.useContext(h);
            if (x) return x;
            if (i !== void 0) return i;
            throw new Error(`\`${f}\` must be used within \`${s}\``)
        }

        return [u, d]
    }

    const o = () => {
        const s = n.map(i => g.createContext(i));
        return function (a) {
            const l = (a == null ? void 0 : a[e]) || s;
            return g.useMemo(() => ({[`__scope${e}`]: {...a, [e]: l}}), [a, l])
        }
    };
    return o.scopeName = e, [r, q_(o, ...t)]
}

function q_(...e) {
    const t = e[0];
    if (e.length === 1) return t;
    const n = () => {
        const r = e.map(o => ({useScope: o(), scopeName: o.scopeName}));
        return function (s) {
            const i = r.reduce((a, {useScope: l, scopeName: u}) => {
                const f = l(s)[`__scope${u}`];
                return {...a, ...f}
            }, {});
            return g.useMemo(() => ({[`__scope${t.scopeName}`]: i}), [i])
        }
    };
    return n.scopeName = t.scopeName, n
}

function Kr(e) {
    const t = Z_(e), n = g.forwardRef((r, o) => {
        const {children: s, ...i} = r, a = g.Children.toArray(s), l = a.find(ej);
        if (l) {
            const u = l.props.children,
                d = a.map(f => f === l ? g.Children.count(u) > 1 ? g.Children.only(null) : g.isValidElement(u) ? u.props.children : null : f);
            return c.jsx(t, {...i, ref: o, children: g.isValidElement(u) ? g.cloneElement(u, void 0, d) : null})
        }
        return c.jsx(t, {...i, ref: o, children: s})
    });
    return n.displayName = `${e}.Slot`, n
}

var Q_ = Kr("Slot");

function Z_(e) {
    const t = g.forwardRef((n, r) => {
        const {children: o, ...s} = n;
        if (g.isValidElement(o)) {
            const i = nj(o), a = tj(s, o.props);
            return o.type !== g.Fragment && (a.ref = r ? Wr(r, i) : i), g.cloneElement(o, a)
        }
        return g.Children.count(o) > 1 ? g.Children.only(null) : null
    });
    return t.displayName = `${e}.SlotClone`, t
}

var Ew = Symbol("radix.slottable");

function J_(e) {
    const t = ({children: n}) => c.jsx(c.Fragment, {children: n});
    return t.displayName = `${e}.Slottable`, t.__radixId = Ew, t
}

function ej(e) {
    return g.isValidElement(e) && typeof e.type == "function" && "__radixId" in e.type && e.type.__radixId === Ew
}

function tj(e, t) {
    const n = {...t};
    for (const r in t) {
        const o = e[r], s = t[r];
        /^on[A-Z]/.test(r) ? o && s ? n[r] = (...a) => {
            s(...a), o(...a)
        } : o && (n[r] = o) : r === "style" ? n[r] = {...o, ...s} : r === "className" && (n[r] = [o, s].filter(Boolean).join(" "))
    }
    return {...e, ...n}
}

function nj(e) {
    var r, o;
    let t = (r = Object.getOwnPropertyDescriptor(e.props, "ref")) == null ? void 0 : r.get,
        n = t && "isReactWarning" in t && t.isReactWarning;
    return n ? e.ref : (t = (o = Object.getOwnPropertyDescriptor(e, "ref")) == null ? void 0 : o.get, n = t && "isReactWarning" in t && t.isReactWarning, n ? e.props.ref : e.props.ref || e.ref)
}

function fu(e) {
    const t = e + "CollectionProvider", [n, r] = mn(t), [o, s] = n(t, {
        collectionRef: {current: null},
        itemMap: new Map
    }), i = m => {
        const {scope: b, children: y} = m, w = Z.useRef(null), v = Z.useRef(new Map).current;
        return c.jsx(o, {scope: b, itemMap: v, collectionRef: w, children: y})
    };
    i.displayName = t;
    const a = e + "CollectionSlot", l = Kr(a), u = Z.forwardRef((m, b) => {
        const {scope: y, children: w} = m, v = s(a, y), C = ge(b, v.collectionRef);
        return c.jsx(l, {ref: C, children: w})
    });
    u.displayName = a;
    const d = e + "CollectionItemSlot", f = "data-radix-collection-item", p = Kr(d), h = Z.forwardRef((m, b) => {
        const {scope: y, children: w, ...v} = m, C = Z.useRef(null), S = ge(b, C), N = s(d, y);
        return Z.useEffect(() => (N.itemMap.set(C, {ref: C, ...v}), () => void N.itemMap.delete(C))), c.jsx(p, {
            [f]: "",
            ref: S,
            children: w
        })
    });
    h.displayName = d;

    function x(m) {
        const b = s(e + "CollectionConsumer", m);
        return Z.useCallback(() => {
            const w = b.collectionRef.current;
            if (!w) return [];
            const v = Array.from(w.querySelectorAll(`[${f}]`));
            return Array.from(b.itemMap.values()).sort((N, E) => v.indexOf(N.ref.current) - v.indexOf(E.ref.current))
        }, [b.collectionRef, b.itemMap])
    }

    return [{Provider: i, Slot: u, ItemSlot: h}, x, r]
}

var rj = ["a", "button", "div", "form", "h2", "h3", "img", "input", "label", "li", "nav", "ol", "p", "span", "svg", "ul"],
    re = rj.reduce((e, t) => {
        const n = Kr(`Primitive.${t}`), r = g.forwardRef((o, s) => {
            const {asChild: i, ...a} = o, l = i ? n : t;
            return typeof window < "u" && (window[Symbol.for("radix-ui")] = !0), c.jsx(l, {...a, ref: s})
        });
        return r.displayName = `Primitive.${t}`, {...e, [t]: r}
    }, {});

function Dh(e, t) {
    e && Fo.flushSync(() => e.dispatchEvent(t))
}

function Xe(e) {
    const t = g.useRef(e);
    return g.useEffect(() => {
        t.current = e
    }), g.useMemo(() => (...n) => {
        var r;
        return (r = t.current) == null ? void 0 : r.call(t, ...n)
    }, [])
}

function oj(e, t = globalThis == null ? void 0 : globalThis.document) {
    const n = Xe(e);
    g.useEffect(() => {
        const r = o => {
            o.key === "Escape" && n(o)
        };
        return t.addEventListener("keydown", r, {capture: !0}), () => t.removeEventListener("keydown", r, {capture: !0})
    }, [n, t])
}

var sj = "DismissableLayer", tp = "dismissableLayer.update", ij = "dismissableLayer.pointerDownOutside",
    aj = "dismissableLayer.focusOutside", av,
    kw = g.createContext({layers: new Set, layersWithOutsidePointerEventsDisabled: new Set, branches: new Set}),
    zo = g.forwardRef((e, t) => {
        const {
                disableOutsidePointerEvents: n = !1,
                onEscapeKeyDown: r,
                onPointerDownOutside: o,
                onFocusOutside: s,
                onInteractOutside: i,
                onDismiss: a,
                ...l
            } = e, u = g.useContext(kw), [d, f] = g.useState(null),
            p = (d == null ? void 0 : d.ownerDocument) ?? (globalThis == null ? void 0 : globalThis.document), [, h] = g.useState({}),
            x = ge(t, E => f(E)),
            m = Array.from(u.layers), [b] = [...u.layersWithOutsidePointerEventsDisabled].slice(-1), y = m.indexOf(b),
            w = d ? m.indexOf(d) : -1, v = u.layersWithOutsidePointerEventsDisabled.size > 0, C = w >= y, S = cj(E => {
                const T = E.target, O = [...u.branches].some(D => D.contains(T));
                !C || O || (o == null || o(E), i == null || i(E), E.defaultPrevented || a == null || a())
            }, p), N = uj(E => {
                const T = E.target;
                [...u.branches].some(D => D.contains(T)) || (s == null || s(E), i == null || i(E), E.defaultPrevented || a == null || a())
            }, p);
        return oj(E => {
            w === u.layers.size - 1 && (r == null || r(E), !E.defaultPrevented && a && (E.preventDefault(), a()))
        }, p), g.useEffect(() => {
            if (d) return n && (u.layersWithOutsidePointerEventsDisabled.size === 0 && (av = p.body.style.pointerEvents, p.body.style.pointerEvents = "none"), u.layersWithOutsidePointerEventsDisabled.add(d)), u.layers.add(d), lv(), () => {
                n && u.layersWithOutsidePointerEventsDisabled.size === 1 && (p.body.style.pointerEvents = av)
            }
        }, [d, p, n, u]), g.useEffect(() => () => {
            d && (u.layers.delete(d), u.layersWithOutsidePointerEventsDisabled.delete(d), lv())
        }, [d, u]), g.useEffect(() => {
            const E = () => h({});
            return document.addEventListener(tp, E), () => document.removeEventListener(tp, E)
        }, []), c.jsx(re.div, {
            ...l,
            ref: x,
            style: {pointerEvents: v ? C ? "auto" : "none" : void 0, ...e.style},
            onFocusCapture: J(e.onFocusCapture, N.onFocusCapture),
            onBlurCapture: J(e.onBlurCapture, N.onBlurCapture),
            onPointerDownCapture: J(e.onPointerDownCapture, S.onPointerDownCapture)
        })
    });
zo.displayName = sj;
var lj = "DismissableLayerBranch", _w = g.forwardRef((e, t) => {
    const n = g.useContext(kw), r = g.useRef(null), o = ge(t, r);
    return g.useEffect(() => {
        const s = r.current;
        if (s) return n.branches.add(s), () => {
            n.branches.delete(s)
        }
    }, [n.branches]), c.jsx(re.div, {...e, ref: o})
});
_w.displayName = lj;

function cj(e, t = globalThis == null ? void 0 : globalThis.document) {
    const n = Xe(e), r = g.useRef(!1), o = g.useRef(() => {
    });
    return g.useEffect(() => {
        const s = a => {
            if (a.target && !r.current) {
                let l = function () {
                    jw(ij, n, u, {discrete: !0})
                };
                const u = {originalEvent: a};
                a.pointerType === "touch" ? (t.removeEventListener("click", o.current), o.current = l, t.addEventListener("click", o.current, {once: !0})) : l()
            } else t.removeEventListener("click", o.current);
            r.current = !1
        }, i = window.setTimeout(() => {
            t.addEventListener("pointerdown", s)
        }, 0);
        return () => {
            window.clearTimeout(i), t.removeEventListener("pointerdown", s), t.removeEventListener("click", o.current)
        }
    }, [t, n]), {onPointerDownCapture: () => r.current = !0}
}

function uj(e, t = globalThis == null ? void 0 : globalThis.document) {
    const n = Xe(e), r = g.useRef(!1);
    return g.useEffect(() => {
        const o = s => {
            s.target && !r.current && jw(aj, n, {originalEvent: s}, {discrete: !1})
        };
        return t.addEventListener("focusin", o), () => t.removeEventListener("focusin", o)
    }, [t, n]), {onFocusCapture: () => r.current = !0, onBlurCapture: () => r.current = !1}
}

function lv() {
    const e = new CustomEvent(tp);
    document.dispatchEvent(e)
}

function jw(e, t, n, {discrete: r}) {
    const o = n.originalEvent.target, s = new CustomEvent(e, {bubbles: !1, cancelable: !0, detail: n});
    t && o.addEventListener(e, t, {once: !0}), r ? Dh(o, s) : o.dispatchEvent(s)
}

var dj = zo, fj = _w, nt = globalThis != null && globalThis.document ? g.useLayoutEffect : () => {
}, pj = "Portal", Qs = g.forwardRef((e, t) => {
    var a;
    const {container: n, ...r} = e, [o, s] = g.useState(!1);
    nt(() => s(!0), []);
    const i = n || o && ((a = globalThis == null ? void 0 : globalThis.document) == null ? void 0 : a.body);
    return i ? Sw.createPortal(c.jsx(re.div, {...r, ref: t}), i) : null
});
Qs.displayName = pj;

function hj(e, t) {
    return g.useReducer((n, r) => t[n][r] ?? n, e)
}

var Dt = e => {
    const {present: t, children: n} = e, r = mj(t),
        o = typeof n == "function" ? n({present: r.isPresent}) : g.Children.only(n), s = ge(r.ref, gj(o));
    return typeof n == "function" || r.isPresent ? g.cloneElement(o, {ref: s}) : null
};
Dt.displayName = "Presence";

function mj(e) {
    const [t, n] = g.useState(), r = g.useRef({}), o = g.useRef(e), s = g.useRef("none"),
        i = e ? "mounted" : "unmounted", [a, l] = hj(i, {
            mounted: {
                UNMOUNT: "unmounted",
                ANIMATION_OUT: "unmountSuspended"
            }, unmountSuspended: {MOUNT: "mounted", ANIMATION_END: "unmounted"}, unmounted: {MOUNT: "mounted"}
        });
    return g.useEffect(() => {
        const u = yl(r.current);
        s.current = a === "mounted" ? u : "none"
    }, [a]), nt(() => {
        const u = r.current, d = o.current;
        if (d !== e) {
            const p = s.current, h = yl(u);
            e ? l("MOUNT") : h === "none" || (u == null ? void 0 : u.display) === "none" ? l("UNMOUNT") : l(d && p !== h ? "ANIMATION_OUT" : "UNMOUNT"), o.current = e
        }
    }, [e, l]), nt(() => {
        if (t) {
            let u;
            const d = t.ownerDocument.defaultView ?? window, f = h => {
                const m = yl(r.current).includes(h.animationName);
                if (h.target === t && m && (l("ANIMATION_END"), !o.current)) {
                    const b = t.style.animationFillMode;
                    t.style.animationFillMode = "forwards", u = d.setTimeout(() => {
                        t.style.animationFillMode === "forwards" && (t.style.animationFillMode = b)
                    })
                }
            }, p = h => {
                h.target === t && (s.current = yl(r.current))
            };
            return t.addEventListener("animationstart", p), t.addEventListener("animationcancel", f), t.addEventListener("animationend", f), () => {
                d.clearTimeout(u), t.removeEventListener("animationstart", p), t.removeEventListener("animationcancel", f), t.removeEventListener("animationend", f)
            }
        } else l("ANIMATION_END")
    }, [t, l]), {
        isPresent: ["mounted", "unmountSuspended"].includes(a), ref: g.useCallback(u => {
            u && (r.current = getComputedStyle(u)), n(u)
        }, [])
    }
}

function yl(e) {
    return (e == null ? void 0 : e.animationName) || "none"
}

function gj(e) {
    var r, o;
    let t = (r = Object.getOwnPropertyDescriptor(e.props, "ref")) == null ? void 0 : r.get,
        n = t && "isReactWarning" in t && t.isReactWarning;
    return n ? e.ref : (t = (o = Object.getOwnPropertyDescriptor(e, "ref")) == null ? void 0 : o.get, n = t && "isReactWarning" in t && t.isReactWarning, n ? e.props.ref : e.props.ref || e.ref)
}

function To({
                prop: e, defaultProp: t, onChange: n = () => {
    }
            }) {
    const [r, o] = vj({defaultProp: t, onChange: n}), s = e !== void 0, i = s ? e : r, a = Xe(n),
        l = g.useCallback(u => {
            if (s) {
                const f = typeof u == "function" ? u(e) : u;
                f !== e && a(f)
            } else o(u)
        }, [s, e, o, a]);
    return [i, l]
}

function vj({defaultProp: e, onChange: t}) {
    const n = g.useState(e), [r] = n, o = g.useRef(r), s = Xe(t);
    return g.useEffect(() => {
        o.current !== r && (s(r), o.current = r)
    }, [r, o, s]), n
}

var yj = "VisuallyHidden", _a = g.forwardRef((e, t) => c.jsx(re.span, {
    ...e,
    ref: t,
    style: {
        position: "absolute",
        border: 0,
        width: 1,
        height: 1,
        padding: 0,
        margin: -1,
        overflow: "hidden",
        clip: "rect(0, 0, 0, 0)",
        whiteSpace: "nowrap",
        wordWrap: "normal", ...e.style
    }
}));
_a.displayName = yj;
var xj = _a, $h = "ToastProvider", [Lh, wj, bj] = fu("Toast"), [Pw, vz] = mn("Toast", [bj]), [Cj, pu] = Pw($h),
    Tw = e => {
        const {
            __scopeToast: t,
            label: n = "Notification",
            duration: r = 5e3,
            swipeDirection: o = "right",
            swipeThreshold: s = 50,
            children: i
        } = e, [a, l] = g.useState(null), [u, d] = g.useState(0), f = g.useRef(!1), p = g.useRef(!1);
        return n.trim() || console.error(`Invalid prop \`label\` supplied to \`${$h}\`. Expected non-empty \`string\`.`), c.jsx(Lh.Provider, {
            scope: t,
            children: c.jsx(Cj, {
                scope: t,
                label: n,
                duration: r,
                swipeDirection: o,
                swipeThreshold: s,
                toastCount: u,
                viewport: a,
                onViewportChange: l,
                onToastAdd: g.useCallback(() => d(h => h + 1), []),
                onToastRemove: g.useCallback(() => d(h => h - 1), []),
                isFocusedToastEscapeKeyDownRef: f,
                isClosePausedRef: p,
                children: i
            })
        })
    };
Tw.displayName = $h;
var Mw = "ToastViewport", Sj = ["F8"], np = "toast.viewportPause", rp = "toast.viewportResume",
    Rw = g.forwardRef((e, t) => {
        const {__scopeToast: n, hotkey: r = Sj, label: o = "Notifications ({hotkey})", ...s} = e, i = pu(Mw, n),
            a = wj(n), l = g.useRef(null), u = g.useRef(null), d = g.useRef(null), f = g.useRef(null),
            p = ge(t, f, i.onViewportChange), h = r.join("+").replace(/Key/g, "").replace(/Digit/g, ""),
            x = i.toastCount > 0;
        g.useEffect(() => {
            const b = y => {
                var v;
                r.length !== 0 && r.every(C => y[C] || y.code === C) && ((v = f.current) == null || v.focus())
            };
            return document.addEventListener("keydown", b), () => document.removeEventListener("keydown", b)
        }, [r]), g.useEffect(() => {
            const b = l.current, y = f.current;
            if (x && b && y) {
                const w = () => {
                    if (!i.isClosePausedRef.current) {
                        const N = new CustomEvent(np);
                        y.dispatchEvent(N), i.isClosePausedRef.current = !0
                    }
                }, v = () => {
                    if (i.isClosePausedRef.current) {
                        const N = new CustomEvent(rp);
                        y.dispatchEvent(N), i.isClosePausedRef.current = !1
                    }
                }, C = N => {
                    !b.contains(N.relatedTarget) && v()
                }, S = () => {
                    b.contains(document.activeElement) || v()
                };
                return b.addEventListener("focusin", w), b.addEventListener("focusout", C), b.addEventListener("pointermove", w), b.addEventListener("pointerleave", S), window.addEventListener("blur", w), window.addEventListener("focus", v), () => {
                    b.removeEventListener("focusin", w), b.removeEventListener("focusout", C), b.removeEventListener("pointermove", w), b.removeEventListener("pointerleave", S), window.removeEventListener("blur", w), window.removeEventListener("focus", v)
                }
            }
        }, [x, i.isClosePausedRef]);
        const m = g.useCallback(({tabbingDirection: b}) => {
            const w = a().map(v => {
                const C = v.ref.current, S = [C, ...Dj(C)];
                return b === "forwards" ? S : S.reverse()
            });
            return (b === "forwards" ? w.reverse() : w).flat()
        }, [a]);
        return g.useEffect(() => {
            const b = f.current;
            if (b) {
                const y = w => {
                    var S, N, E;
                    const v = w.altKey || w.ctrlKey || w.metaKey;
                    if (w.key === "Tab" && !v) {
                        const T = document.activeElement, O = w.shiftKey;
                        if (w.target === b && O) {
                            (S = u.current) == null || S.focus();
                            return
                        }
                        const $ = m({tabbingDirection: O ? "backwards" : "forwards"}), B = $.findIndex(k => k === T);
                        Dd($.slice(B + 1)) ? w.preventDefault() : O ? (N = u.current) == null || N.focus() : (E = d.current) == null || E.focus()
                    }
                };
                return b.addEventListener("keydown", y), () => b.removeEventListener("keydown", y)
            }
        }, [a, m]), c.jsxs(fj, {
            ref: l,
            role: "region",
            "aria-label": o.replace("{hotkey}", h),
            tabIndex: -1,
            style: {pointerEvents: x ? void 0 : "none"},
            children: [x && c.jsx(op, {
                ref: u, onFocusFromOutsideViewport: () => {
                    const b = m({tabbingDirection: "forwards"});
                    Dd(b)
                }
            }), c.jsx(Lh.Slot, {
                scope: n,
                children: c.jsx(re.ol, {tabIndex: -1, ...s, ref: p})
            }), x && c.jsx(op, {
                ref: d, onFocusFromOutsideViewport: () => {
                    const b = m({tabbingDirection: "backwards"});
                    Dd(b)
                }
            })]
        })
    });
Rw.displayName = Mw;
var Aw = "ToastFocusProxy", op = g.forwardRef((e, t) => {
    const {__scopeToast: n, onFocusFromOutsideViewport: r, ...o} = e, s = pu(Aw, n);
    return c.jsx(_a, {
        "aria-hidden": !0, tabIndex: 0, ...o, ref: t, style: {position: "fixed"}, onFocus: i => {
            var u;
            const a = i.relatedTarget;
            !((u = s.viewport) != null && u.contains(a)) && r()
        }
    })
});
op.displayName = Aw;
var hu = "Toast", Nj = "toast.swipeStart", Ej = "toast.swipeMove", kj = "toast.swipeCancel", _j = "toast.swipeEnd",
    Iw = g.forwardRef((e, t) => {
        const {forceMount: n, open: r, defaultOpen: o, onOpenChange: s, ...i} = e, [a = !0, l] = To({
            prop: r,
            defaultProp: o,
            onChange: s
        });
        return c.jsx(Dt, {
            present: n || a, children: c.jsx(Tj, {
                open: a, ...i,
                ref: t,
                onClose: () => l(!1),
                onPause: Xe(e.onPause),
                onResume: Xe(e.onResume),
                onSwipeStart: J(e.onSwipeStart, u => {
                    u.currentTarget.setAttribute("data-swipe", "start")
                }),
                onSwipeMove: J(e.onSwipeMove, u => {
                    const {x: d, y: f} = u.detail.delta;
                    u.currentTarget.setAttribute("data-swipe", "move"), u.currentTarget.style.setProperty("--radix-toast-swipe-move-x", `${d}px`), u.currentTarget.style.setProperty("--radix-toast-swipe-move-y", `${f}px`)
                }),
                onSwipeCancel: J(e.onSwipeCancel, u => {
                    u.currentTarget.setAttribute("data-swipe", "cancel"), u.currentTarget.style.removeProperty("--radix-toast-swipe-move-x"), u.currentTarget.style.removeProperty("--radix-toast-swipe-move-y"), u.currentTarget.style.removeProperty("--radix-toast-swipe-end-x"), u.currentTarget.style.removeProperty("--radix-toast-swipe-end-y")
                }),
                onSwipeEnd: J(e.onSwipeEnd, u => {
                    const {x: d, y: f} = u.detail.delta;
                    u.currentTarget.setAttribute("data-swipe", "end"), u.currentTarget.style.removeProperty("--radix-toast-swipe-move-x"), u.currentTarget.style.removeProperty("--radix-toast-swipe-move-y"), u.currentTarget.style.setProperty("--radix-toast-swipe-end-x", `${d}px`), u.currentTarget.style.setProperty("--radix-toast-swipe-end-y", `${f}px`), l(!1)
                })
            })
        })
    });
Iw.displayName = hu;
var [jj, Pj] = Pw(hu, {
    onClose() {
    }
}), Tj = g.forwardRef((e, t) => {
    const {
            __scopeToast: n,
            type: r = "foreground",
            duration: o,
            open: s,
            onClose: i,
            onEscapeKeyDown: a,
            onPause: l,
            onResume: u,
            onSwipeStart: d,
            onSwipeMove: f,
            onSwipeCancel: p,
            onSwipeEnd: h,
            ...x
        } = e, m = pu(hu, n), [b, y] = g.useState(null), w = ge(t, k => y(k)), v = g.useRef(null), C = g.useRef(null),
        S = o || m.duration, N = g.useRef(0), E = g.useRef(S), T = g.useRef(0), {onToastAdd: O, onToastRemove: D} = m,
        L = Xe(() => {
            var M;
            (b == null ? void 0 : b.contains(document.activeElement)) && ((M = m.viewport) == null || M.focus()), i()
        }), $ = g.useCallback(k => {
            !k || k === 1 / 0 || (window.clearTimeout(T.current), N.current = new Date().getTime(), T.current = window.setTimeout(L, k))
        }, [L]);
    g.useEffect(() => {
        const k = m.viewport;
        if (k) {
            const M = () => {
                $(E.current), u == null || u()
            }, j = () => {
                const A = new Date().getTime() - N.current;
                E.current = E.current - A, window.clearTimeout(T.current), l == null || l()
            };
            return k.addEventListener(np, j), k.addEventListener(rp, M), () => {
                k.removeEventListener(np, j), k.removeEventListener(rp, M)
            }
        }
    }, [m.viewport, S, l, u, $]), g.useEffect(() => {
        s && !m.isClosePausedRef.current && $(S)
    }, [s, S, m.isClosePausedRef, $]), g.useEffect(() => (O(), () => D()), [O, D]);
    const B = g.useMemo(() => b ? Bw(b) : null, [b]);
    return m.viewport ? c.jsxs(c.Fragment, {
        children: [B && c.jsx(Mj, {
            __scopeToast: n,
            role: "status",
            "aria-live": r === "foreground" ? "assertive" : "polite",
            "aria-atomic": !0,
            children: B
        }), c.jsx(jj, {
            scope: n, onClose: L, children: Fo.createPortal(c.jsx(Lh.ItemSlot, {
                scope: n, children: c.jsx(dj, {
                    asChild: !0, onEscapeKeyDown: J(a, () => {
                        m.isFocusedToastEscapeKeyDownRef.current || L(), m.isFocusedToastEscapeKeyDownRef.current = !1
                    }), children: c.jsx(re.li, {
                        role: "status",
                        "aria-live": "off",
                        "aria-atomic": !0,
                        tabIndex: 0,
                        "data-state": s ? "open" : "closed",
                        "data-swipe-direction": m.swipeDirection, ...x,
                        ref: w,
                        style: {userSelect: "none", touchAction: "none", ...e.style},
                        onKeyDown: J(e.onKeyDown, k => {
                            k.key === "Escape" && (a == null || a(k.nativeEvent), k.nativeEvent.defaultPrevented || (m.isFocusedToastEscapeKeyDownRef.current = !0, L()))
                        }),
                        onPointerDown: J(e.onPointerDown, k => {
                            k.button === 0 && (v.current = {x: k.clientX, y: k.clientY})
                        }),
                        onPointerMove: J(e.onPointerMove, k => {
                            if (!v.current) return;
                            const M = k.clientX - v.current.x, j = k.clientY - v.current.y, A = !!C.current,
                                P = ["left", "right"].includes(m.swipeDirection),
                                _ = ["left", "up"].includes(m.swipeDirection) ? Math.min : Math.max,
                                R = P ? _(0, M) : 0, z = P ? 0 : _(0, j), I = k.pointerType === "touch" ? 10 : 2,
                                V = {x: R, y: z}, H = {originalEvent: k, delta: V};
                            A ? (C.current = V, xl(Ej, f, H, {discrete: !1})) : cv(V, m.swipeDirection, I) ? (C.current = V, xl(Nj, d, H, {discrete: !1}), k.target.setPointerCapture(k.pointerId)) : (Math.abs(M) > I || Math.abs(j) > I) && (v.current = null)
                        }),
                        onPointerUp: J(e.onPointerUp, k => {
                            const M = C.current, j = k.target;
                            if (j.hasPointerCapture(k.pointerId) && j.releasePointerCapture(k.pointerId), C.current = null, v.current = null, M) {
                                const A = k.currentTarget, P = {originalEvent: k, delta: M};
                                cv(M, m.swipeDirection, m.swipeThreshold) ? xl(_j, h, P, {discrete: !0}) : xl(kj, p, P, {discrete: !0}), A.addEventListener("click", _ => _.preventDefault(), {once: !0})
                            }
                        })
                    })
                })
            }), m.viewport)
        })]
    }) : null
}), Mj = e => {
    const {__scopeToast: t, children: n, ...r} = e, o = pu(hu, t), [s, i] = g.useState(!1), [a, l] = g.useState(!1);
    return Ij(() => i(!0)), g.useEffect(() => {
        const u = window.setTimeout(() => l(!0), 1e3);
        return () => window.clearTimeout(u)
    }, []), a ? null : c.jsx(Qs, {
        asChild: !0,
        children: c.jsx(_a, {...r, children: s && c.jsxs(c.Fragment, {children: [o.label, " ", n]})})
    })
}, Rj = "ToastTitle", Ow = g.forwardRef((e, t) => {
    const {__scopeToast: n, ...r} = e;
    return c.jsx(re.div, {...r, ref: t})
});
Ow.displayName = Rj;
var Aj = "ToastDescription", Dw = g.forwardRef((e, t) => {
    const {__scopeToast: n, ...r} = e;
    return c.jsx(re.div, {...r, ref: t})
});
Dw.displayName = Aj;
var $w = "ToastAction", Lw = g.forwardRef((e, t) => {
    const {altText: n, ...r} = e;
    return n.trim() ? c.jsx(zw, {
        altText: n,
        asChild: !0,
        children: c.jsx(Fh, {...r, ref: t})
    }) : (console.error(`Invalid prop \`altText\` supplied to \`${$w}\`. Expected non-empty \`string\`.`), null)
});
Lw.displayName = $w;
var Fw = "ToastClose", Fh = g.forwardRef((e, t) => {
    const {__scopeToast: n, ...r} = e, o = Pj(Fw, n);
    return c.jsx(zw, {
        asChild: !0,
        children: c.jsx(re.button, {type: "button", ...r, ref: t, onClick: J(e.onClick, o.onClose)})
    })
});
Fh.displayName = Fw;
var zw = g.forwardRef((e, t) => {
    const {__scopeToast: n, altText: r, ...o} = e;
    return c.jsx(re.div, {
        "data-radix-toast-announce-exclude": "",
        "data-radix-toast-announce-alt": r || void 0, ...o,
        ref: t
    })
});

function Bw(e) {
    const t = [];
    return Array.from(e.childNodes).forEach(r => {
        if (r.nodeType === r.TEXT_NODE && r.textContent && t.push(r.textContent), Oj(r)) {
            const o = r.ariaHidden || r.hidden || r.style.display === "none",
                s = r.dataset.radixToastAnnounceExclude === "";
            if (!o) if (s) {
                const i = r.dataset.radixToastAnnounceAlt;
                i && t.push(i)
            } else t.push(...Bw(r))
        }
    }), t
}

function xl(e, t, n, {discrete: r}) {
    const o = n.originalEvent.currentTarget, s = new CustomEvent(e, {bubbles: !0, cancelable: !0, detail: n});
    t && o.addEventListener(e, t, {once: !0}), r ? Dh(o, s) : o.dispatchEvent(s)
}

var cv = (e, t, n = 0) => {
    const r = Math.abs(e.x), o = Math.abs(e.y), s = r > o;
    return t === "left" || t === "right" ? s && r > n : !s && o > n
};

function Ij(e = () => {
}) {
    const t = Xe(e);
    nt(() => {
        let n = 0, r = 0;
        return n = window.requestAnimationFrame(() => r = window.requestAnimationFrame(t)), () => {
            window.cancelAnimationFrame(n), window.cancelAnimationFrame(r)
        }
    }, [t])
}

function Oj(e) {
    return e.nodeType === e.ELEMENT_NODE
}

function Dj(e) {
    const t = [], n = document.createTreeWalker(e, NodeFilter.SHOW_ELEMENT, {
        acceptNode: r => {
            const o = r.tagName === "INPUT" && r.type === "hidden";
            return r.disabled || r.hidden || o ? NodeFilter.FILTER_SKIP : r.tabIndex >= 0 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP
        }
    });
    for (; n.nextNode();) t.push(n.currentNode);
    return t
}

function Dd(e) {
    const t = document.activeElement;
    return e.some(n => n === t ? !0 : (n.focus(), document.activeElement !== t))
}

var $j = Tw, Vw = Rw, Hw = Iw, Uw = Ow, Ww = Dw, Kw = Lw, Yw = Fh;

function Gw(e) {
    var t, n, r = "";
    if (typeof e == "string" || typeof e == "number") r += e; else if (typeof e == "object") if (Array.isArray(e)) {
        var o = e.length;
        for (t = 0; t < o; t++) e[t] && (n = Gw(e[t])) && (r && (r += " "), r += n)
    } else for (n in e) e[n] && (r && (r += " "), r += n);
    return r
}

function Xw() {
    for (var e, t, n = 0, r = "", o = arguments.length; n < o; n++) (e = arguments[n]) && (t = Gw(e)) && (r && (r += " "), r += t);
    return r
}

const uv = e => typeof e == "boolean" ? `${e}` : e === 0 ? "0" : e, dv = Xw, mu = (e, t) => n => {
    var r;
    if ((t == null ? void 0 : t.variants) == null) return dv(e, n == null ? void 0 : n.class, n == null ? void 0 : n.className);
    const {variants: o, defaultVariants: s} = t, i = Object.keys(o).map(u => {
        const d = n == null ? void 0 : n[u], f = s == null ? void 0 : s[u];
        if (d === null) return null;
        const p = uv(d) || uv(f);
        return o[u][p]
    }), a = n && Object.entries(n).reduce((u, d) => {
        let [f, p] = d;
        return p === void 0 || (u[f] = p), u
    }, {}), l = t == null || (r = t.compoundVariants) === null || r === void 0 ? void 0 : r.reduce((u, d) => {
        let {class: f, className: p, ...h} = d;
        return Object.entries(h).every(x => {
            let [m, b] = x;
            return Array.isArray(b) ? b.includes({...s, ...a}[m]) : {...s, ...a}[m] === b
        }) ? [...u, f, p] : u
    }, []);
    return dv(e, i, l, n == null ? void 0 : n.class, n == null ? void 0 : n.className)
};
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Lj = e => e.replace(/([a-z0-9])([A-Z])/g, "$1-$2").toLowerCase(),
    qw = (...e) => e.filter((t, n, r) => !!t && t.trim() !== "" && r.indexOf(t) === n).join(" ").trim();
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var Fj = {
    xmlns: "http://www.w3.org/2000/svg",
    width: 24,
    height: 24,
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 2,
    strokeLinecap: "round",
    strokeLinejoin: "round"
};
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const zj = g.forwardRef(({
                                color: e = "currentColor",
                                size: t = 24,
                                strokeWidth: n = 2,
                                absoluteStrokeWidth: r,
                                className: o = "",
                                children: s,
                                iconNode: i,
                                ...a
                            }, l) => g.createElement("svg", {
    ref: l, ...Fj,
    width: t,
    height: t,
    stroke: e,
    strokeWidth: r ? Number(n) * 24 / Number(t) : n,
    className: qw("lucide", o), ...a
}, [...i.map(([u, d]) => g.createElement(u, d)), ...Array.isArray(s) ? s : [s]]));
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const fe = (e, t) => {
    const n = g.forwardRef(({className: r, ...o}, s) => g.createElement(zj, {
        ref: s,
        iconNode: t,
        className: qw(`lucide-${Lj(e)}`, r), ...o
    }));
    return n.displayName = `${e}`, n
};
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Bj = fe("ArrowLeft", [["path", {d: "m12 19-7-7 7-7", key: "1l729n"}], ["path", {
    d: "M19 12H5",
    key: "x3x0zl"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Qw = fe("Bot", [["path", {d: "M12 8V4H8", key: "hb8ula"}], ["rect", {
    width: "16",
    height: "12",
    x: "4",
    y: "8",
    rx: "2",
    key: "enze0r"
}], ["path", {d: "M2 14h2", key: "vft8re"}], ["path", {d: "M20 14h2", key: "4cs60a"}], ["path", {
    d: "M15 13v2",
    key: "1xurst"
}], ["path", {d: "M9 13v2", key: "rq6x2g"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Zw = fe("BrainCog", [["path", {
    d: "M12 5a3 3 0 1 0-5.997.142 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588 4 4 0 0 0 7.636 2.106 3.2 3.2 0 0 0 .164-.546c.028-.13.306-.13.335 0a3.2 3.2 0 0 0 .163.546 4 4 0 0 0 7.636-2.106 4 4 0 0 0 .556-6.588 4 4 0 0 0-2.526-5.77A3 3 0 1 0 12 5",
    key: "1kgmhc"
}], ["path", {d: "M17.599 6.5a3 3 0 0 0 .399-1.375", key: "tmeiqw"}], ["path", {
    d: "M6.003 5.125A3 3 0 0 0 6.401 6.5",
    key: "105sqy"
}], ["path", {d: "M3.477 10.896a4 4 0 0 1 .585-.396", key: "ql3yin"}], ["path", {
    d: "M19.938 10.5a4 4 0 0 1 .585.396",
    key: "1qfode"
}], ["path", {d: "M6 18a4 4 0 0 1-1.967-.516", key: "2e4loj"}], ["path", {
    d: "M19.967 17.484A4 4 0 0 1 18 18",
    key: "159ez6"
}], ["circle", {cx: "12", cy: "12", r: "3", key: "1v7zrd"}], ["path", {
    d: "m15.7 10.4-.9.4",
    key: "ayzo6p"
}], ["path", {d: "m9.2 13.2-.9.4", key: "1uzb3g"}], ["path", {
    d: "m13.6 15.7-.4-.9",
    key: "11ifqf"
}], ["path", {d: "m10.8 9.2-.4-.9", key: "1pmk2v"}], ["path", {
    d: "m15.7 13.5-.9-.4",
    key: "7ng02m"
}], ["path", {d: "m9.2 10.9-.9-.4", key: "1x66zd"}], ["path", {
    d: "m10.5 15.7.4-.9",
    key: "3js94g"
}], ["path", {d: "m13.1 9.2.4-.9", key: "18n7mc"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Vj = fe("Brain", [["path", {
    d: "M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z",
    key: "l5xja"
}], ["path", {
    d: "M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z",
    key: "ep3f8r"
}], ["path", {
    d: "M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4",
    key: "1p4c4q"
}], ["path", {d: "M17.599 6.5a3 3 0 0 0 .399-1.375", key: "tmeiqw"}], ["path", {
    d: "M6.003 5.125A3 3 0 0 0 6.401 6.5",
    key: "105sqy"
}], ["path", {d: "M3.477 10.896a4 4 0 0 1 .585-.396", key: "ql3yin"}], ["path", {
    d: "M19.938 10.5a4 4 0 0 1 .585.396",
    key: "1qfode"
}], ["path", {d: "M6 18a4 4 0 0 1-1.967-.516", key: "2e4loj"}], ["path", {
    d: "M19.967 17.484A4 4 0 0 1 18 18",
    key: "159ez6"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const zh = fe("Check", [["path", {d: "M20 6 9 17l-5-5", key: "1gmf2c"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Jw = fe("ChevronDown", [["path", {d: "m6 9 6 6 6-6", key: "qrunsl"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Hj = fe("ChevronRight", [["path", {d: "m9 18 6-6-6-6", key: "mthhwq"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Uj = fe("ChevronUp", [["path", {d: "m18 15-6-6-6 6", key: "153udz"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const e1 = fe("CircleHelp", [["circle", {
    cx: "12",
    cy: "12",
    r: "10",
    key: "1mglay"
}], ["path", {d: "M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3", key: "1u773s"}], ["path", {d: "M12 17h.01", key: "p32p05"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Wj = fe("Circle", [["circle", {cx: "12", cy: "12", r: "10", key: "1mglay"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Bh = fe("Clock", [["circle", {
    cx: "12",
    cy: "12",
    r: "10",
    key: "1mglay"
}], ["polyline", {points: "12 6 12 12 16 14", key: "68esgv"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Kj = fe("Copy", [["rect", {
    width: "14",
    height: "14",
    x: "8",
    y: "8",
    rx: "2",
    ry: "2",
    key: "17jyea"
}], ["path", {d: "M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2", key: "zix9uf"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Yj = fe("Database", [["ellipse", {
    cx: "12",
    cy: "5",
    rx: "9",
    ry: "3",
    key: "msslwz"
}], ["path", {d: "M3 5V19A9 3 0 0 0 21 19V5", key: "1wlel7"}], ["path", {d: "M3 12A9 3 0 0 0 21 12", key: "mv7ke4"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const bo = fe("FileText", [["path", {
    d: "M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z",
    key: "1rqfz7"
}], ["path", {d: "M14 2v4a2 2 0 0 0 2 2h4", key: "tnqrlb"}], ["path", {
    d: "M10 9H8",
    key: "b1mrlr"
}], ["path", {d: "M16 13H8", key: "t4e002"}], ["path", {d: "M16 17H8", key: "z1uh3a"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Gj = fe("FileVideo", [["path", {
    d: "M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z",
    key: "1rqfz7"
}], ["path", {d: "M14 2v4a2 2 0 0 0 2 2h4", key: "tnqrlb"}], ["path", {d: "m10 11 5 3-5 3v-6Z", key: "7ntvm4"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const sp = fe("File", [["path", {
    d: "M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z",
    key: "1rqfz7"
}], ["path", {d: "M14 2v4a2 2 0 0 0 2 2h4", key: "tnqrlb"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const t1 = fe("GitFork", [["circle", {cx: "12", cy: "18", r: "3", key: "1mpf1b"}], ["circle", {
    cx: "6",
    cy: "6",
    r: "3",
    key: "1lh9wr"
}], ["circle", {cx: "18", cy: "6", r: "3", key: "1h7g24"}], ["path", {
    d: "M18 9v2c0 .6-.4 1-1 1H7c-.6 0-1-.4-1-1V9",
    key: "1uq4wg"
}], ["path", {d: "M12 12v3", key: "158kv8"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const n1 = fe("GitMerge", [["circle", {cx: "18", cy: "18", r: "3", key: "1xkwt0"}], ["circle", {
    cx: "6",
    cy: "6",
    r: "3",
    key: "1lh9wr"
}], ["path", {d: "M6 21V9a9 9 0 0 0 9 9", key: "7kw0sc"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ra = fe("Globe", [["circle", {
    cx: "12",
    cy: "12",
    r: "10",
    key: "1mglay"
}], ["path", {d: "M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20", key: "13o1zl"}], ["path", {
    d: "M2 12h20",
    key: "9i4pu4"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Vh = fe("Image", [["rect", {
    width: "18",
    height: "18",
    x: "3",
    y: "3",
    rx: "2",
    ry: "2",
    key: "1m3agn"
}], ["circle", {cx: "9", cy: "9", r: "2", key: "af1f0g"}], ["path", {
    d: "m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21",
    key: "1xmnt7"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Xj = fe("KeyRound", [["path", {
    d: "M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z",
    key: "1s6t7t"
}], ["circle", {cx: "16.5", cy: "7.5", r: ".5", fill: "currentColor", key: "w0ekpg"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const r1 = fe("Key", [["path", {
    d: "m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4",
    key: "g0fldk"
}], ["path", {d: "m21 2-9.6 9.6", key: "1j0ho8"}], ["circle", {cx: "7.5", cy: "15.5", r: "5.5", key: "yqb3hr"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Hh = fe("MessageCircle", [["path", {d: "M7.9 20A9 9 0 1 0 4 16.1L2 22Z", key: "vv11sd"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ja = fe("MessageSquare", [["path", {
    d: "M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z",
    key: "1lielz"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const qj = fe("Music", [["path", {d: "M9 18V5l12-2v13", key: "1jmyc2"}], ["circle", {
    cx: "6",
    cy: "18",
    r: "3",
    key: "fqmcym"
}], ["circle", {cx: "18", cy: "16", r: "3", key: "1hluhg"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Qj = fe("Phone", [["path", {
    d: "M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z",
    key: "foiqr5"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Pa = fe("Plus", [["path", {d: "M5 12h14", key: "1ays0h"}], ["path", {d: "M12 5v14", key: "s699le"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Zj = fe("Save", [["path", {
    d: "M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z",
    key: "1c8476"
}], ["path", {d: "M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7", key: "1ydtos"}], ["path", {
    d: "M7 3v4a1 1 0 0 0 1 1h7",
    key: "t51u73"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Jj = fe("Search", [["circle", {cx: "11", cy: "11", r: "8", key: "4ej97u"}], ["path", {
    d: "m21 21-4.3-4.3",
    key: "1qie3q"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const eP = fe("Sparkles", [["path", {
    d: "M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z",
    key: "4pj2yx"
}], ["path", {d: "M20 3v4", key: "1olli1"}], ["path", {d: "M22 5h-4", key: "1gvqau"}], ["path", {
    d: "M4 17v2",
    key: "vumght"
}], ["path", {d: "M5 18H3", key: "zchphs"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const o1 = fe("Square", [["rect", {width: "18", height: "18", x: "3", y: "3", rx: "2", key: "afitv7"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const at = fe("Trash2", [["path", {
    d: "M3 6h18",
    key: "d0wm0j"
}], ["path", {
    d: "M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6",
    key: "4alrt4"
}], ["path", {d: "M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2", key: "v07s0e"}], ["line", {
    x1: "10",
    x2: "10",
    y1: "11",
    y2: "17",
    key: "1uufr5"
}], ["line", {x1: "14", x2: "14", y1: "11", y2: "17", key: "xtxkd"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Co = fe("Upload", [["path", {
    d: "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",
    key: "ih7n3h"
}], ["polyline", {points: "17 8 12 3 7 8", key: "t8dd8p"}], ["line", {
    x1: "12",
    x2: "12",
    y1: "3",
    y2: "15",
    key: "widbto"
}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const gu = fe("Variable", [["path", {
    d: "M8 21s-4-3-4-9 4-9 4-9",
    key: "uto9ud"
}], ["path", {d: "M16 3s4 3 4 9-4 9-4 9", key: "4w2vsq"}], ["line", {
    x1: "15",
    x2: "9",
    y1: "9",
    y2: "15",
    key: "f7djnv"
}], ["line", {x1: "9", x2: "15", y1: "9", y2: "15", key: "1shsy8"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const s1 = fe("Video", [["path", {
    d: "m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5",
    key: "ftymec"
}], ["rect", {x: "2", y: "6", width: "14", height: "12", rx: "2", key: "158x01"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const i1 = fe("Webhook", [["path", {
    d: "M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.57-2",
    key: "q3hayz"
}], ["path", {
    d: "m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.89-4.06",
    key: "1go1hn"
}], ["path", {d: "m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8", key: "qlwsc0"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Zs = fe("X", [["path", {d: "M18 6 6 18", key: "1bl5f8"}], ["path", {d: "m6 6 12 12", key: "d8bk6v"}]]);
/**
 * @license lucide-react v0.462.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const tP = fe("Zap", [["path", {
    d: "M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z",
    key: "1xq2db"
}]]), Uh = "-", nP = e => {
    const t = oP(e), {conflictingClassGroups: n, conflictingClassGroupModifiers: r} = e;
    return {
        getClassGroupId: i => {
            const a = i.split(Uh);
            return a[0] === "" && a.length !== 1 && a.shift(), a1(a, t) || rP(i)
        }, getConflictingClassGroupIds: (i, a) => {
            const l = n[i] || [];
            return a && r[i] ? [...l, ...r[i]] : l
        }
    }
}, a1 = (e, t) => {
    var i;
    if (e.length === 0) return t.classGroupId;
    const n = e[0], r = t.nextPart.get(n), o = r ? a1(e.slice(1), r) : void 0;
    if (o) return o;
    if (t.validators.length === 0) return;
    const s = e.join(Uh);
    return (i = t.validators.find(({validator: a}) => a(s))) == null ? void 0 : i.classGroupId
}, fv = /^\[(.+)\]$/, rP = e => {
    if (fv.test(e)) {
        const t = fv.exec(e)[1], n = t == null ? void 0 : t.substring(0, t.indexOf(":"));
        if (n) return "arbitrary.." + n
    }
}, oP = e => {
    const {theme: t, prefix: n} = e, r = {nextPart: new Map, validators: []};
    return iP(Object.entries(e.classGroups), n).forEach(([s, i]) => {
        ip(i, r, s, t)
    }), r
}, ip = (e, t, n, r) => {
    e.forEach(o => {
        if (typeof o == "string") {
            const s = o === "" ? t : pv(t, o);
            s.classGroupId = n;
            return
        }
        if (typeof o == "function") {
            if (sP(o)) {
                ip(o(r), t, n, r);
                return
            }
            t.validators.push({validator: o, classGroupId: n});
            return
        }
        Object.entries(o).forEach(([s, i]) => {
            ip(i, pv(t, s), n, r)
        })
    })
}, pv = (e, t) => {
    let n = e;
    return t.split(Uh).forEach(r => {
        n.nextPart.has(r) || n.nextPart.set(r, {nextPart: new Map, validators: []}), n = n.nextPart.get(r)
    }), n
}, sP = e => e.isThemeGetter, iP = (e, t) => t ? e.map(([n, r]) => {
    const o = r.map(s => typeof s == "string" ? t + s : typeof s == "object" ? Object.fromEntries(Object.entries(s).map(([i, a]) => [t + i, a])) : s);
    return [n, o]
}) : e, aP = e => {
    if (e < 1) return {
        get: () => {
        }, set: () => {
        }
    };
    let t = 0, n = new Map, r = new Map;
    const o = (s, i) => {
        n.set(s, i), t++, t > e && (t = 0, r = n, n = new Map)
    };
    return {
        get(s) {
            let i = n.get(s);
            if (i !== void 0) return i;
            if ((i = r.get(s)) !== void 0) return o(s, i), i
        }, set(s, i) {
            n.has(s) ? n.set(s, i) : o(s, i)
        }
    }
}, l1 = "!", lP = e => {
    const {separator: t, experimentalParseClassName: n} = e, r = t.length === 1, o = t[0], s = t.length, i = a => {
        const l = [];
        let u = 0, d = 0, f;
        for (let b = 0; b < a.length; b++) {
            let y = a[b];
            if (u === 0) {
                if (y === o && (r || a.slice(b, b + s) === t)) {
                    l.push(a.slice(d, b)), d = b + s;
                    continue
                }
                if (y === "/") {
                    f = b;
                    continue
                }
            }
            y === "[" ? u++ : y === "]" && u--
        }
        const p = l.length === 0 ? a : a.substring(d), h = p.startsWith(l1), x = h ? p.substring(1) : p,
            m = f && f > d ? f - d : void 0;
        return {modifiers: l, hasImportantModifier: h, baseClassName: x, maybePostfixModifierPosition: m}
    };
    return n ? a => n({className: a, parseClassName: i}) : i
}, cP = e => {
    if (e.length <= 1) return e;
    const t = [];
    let n = [];
    return e.forEach(r => {
        r[0] === "[" ? (t.push(...n.sort(), r), n = []) : n.push(r)
    }), t.push(...n.sort()), t
}, uP = e => ({cache: aP(e.cacheSize), parseClassName: lP(e), ...nP(e)}), dP = /\s+/, fP = (e, t) => {
    const {parseClassName: n, getClassGroupId: r, getConflictingClassGroupIds: o} = t, s = [], i = e.trim().split(dP);
    let a = "";
    for (let l = i.length - 1; l >= 0; l -= 1) {
        const u = i[l], {
            modifiers: d,
            hasImportantModifier: f,
            baseClassName: p,
            maybePostfixModifierPosition: h
        } = n(u);
        let x = !!h, m = r(x ? p.substring(0, h) : p);
        if (!m) {
            if (!x) {
                a = u + (a.length > 0 ? " " + a : a);
                continue
            }
            if (m = r(p), !m) {
                a = u + (a.length > 0 ? " " + a : a);
                continue
            }
            x = !1
        }
        const b = cP(d).join(":"), y = f ? b + l1 : b, w = y + m;
        if (s.includes(w)) continue;
        s.push(w);
        const v = o(m, x);
        for (let C = 0; C < v.length; ++C) {
            const S = v[C];
            s.push(y + S)
        }
        a = u + (a.length > 0 ? " " + a : a)
    }
    return a
};

function pP() {
    let e = 0, t, n, r = "";
    for (; e < arguments.length;) (t = arguments[e++]) && (n = c1(t)) && (r && (r += " "), r += n);
    return r
}

const c1 = e => {
    if (typeof e == "string") return e;
    let t, n = "";
    for (let r = 0; r < e.length; r++) e[r] && (t = c1(e[r])) && (n && (n += " "), n += t);
    return n
};

function hP(e, ...t) {
    let n, r, o, s = i;

    function i(l) {
        const u = t.reduce((d, f) => f(d), e());
        return n = uP(u), r = n.cache.get, o = n.cache.set, s = a, a(l)
    }

    function a(l) {
        const u = r(l);
        if (u) return u;
        const d = fP(l, n);
        return o(l, d), d
    }

    return function () {
        return s(pP.apply(null, arguments))
    }
}

const Ee = e => {
        const t = n => n[e] || [];
        return t.isThemeGetter = !0, t
    }, u1 = /^\[(?:([a-z-]+):)?(.+)\]$/i, mP = /^\d+\/\d+$/, gP = new Set(["px", "full", "screen"]),
    vP = /^(\d+(\.\d+)?)?(xs|sm|md|lg|xl)$/,
    yP = /\d+(%|px|r?em|[sdl]?v([hwib]|min|max)|pt|pc|in|cm|mm|cap|ch|ex|r?lh|cq(w|h|i|b|min|max))|\b(calc|min|max|clamp)\(.+\)|^0$/,
    xP = /^(rgba?|hsla?|hwb|(ok)?(lab|lch))\(.+\)$/,
    wP = /^(inset_)?-?((\d+)?\.?(\d+)[a-z]+|0)_-?((\d+)?\.?(\d+)[a-z]+|0)/,
    bP = /^(url|image|image-set|cross-fade|element|(repeating-)?(linear|radial|conic)-gradient)\(.+\)$/,
    zn = e => vs(e) || gP.has(e) || mP.test(e), mr = e => Js(e, "length", PP),
    vs = e => !!e && !Number.isNaN(Number(e)), $d = e => Js(e, "number", vs),
    mi = e => !!e && Number.isInteger(Number(e)), CP = e => e.endsWith("%") && vs(e.slice(0, -1)), ce = e => u1.test(e),
    gr = e => vP.test(e), SP = new Set(["length", "size", "percentage"]), NP = e => Js(e, SP, d1),
    EP = e => Js(e, "position", d1), kP = new Set(["image", "url"]), _P = e => Js(e, kP, MP), jP = e => Js(e, "", TP),
    gi = () => !0, Js = (e, t, n) => {
        const r = u1.exec(e);
        return r ? r[1] ? typeof t == "string" ? r[1] === t : t.has(r[1]) : n(r[2]) : !1
    }, PP = e => yP.test(e) && !xP.test(e), d1 = () => !1, TP = e => wP.test(e), MP = e => bP.test(e), RP = () => {
        const e = Ee("colors"), t = Ee("spacing"), n = Ee("blur"), r = Ee("brightness"), o = Ee("borderColor"),
            s = Ee("borderRadius"), i = Ee("borderSpacing"), a = Ee("borderWidth"), l = Ee("contrast"), u = Ee("grayscale"),
            d = Ee("hueRotate"), f = Ee("invert"), p = Ee("gap"), h = Ee("gradientColorStops"),
            x = Ee("gradientColorStopPositions"), m = Ee("inset"), b = Ee("margin"), y = Ee("opacity"), w = Ee("padding"),
            v = Ee("saturate"), C = Ee("scale"), S = Ee("sepia"), N = Ee("skew"), E = Ee("space"), T = Ee("translate"),
            O = () => ["auto", "contain", "none"], D = () => ["auto", "hidden", "clip", "visible", "scroll"],
            L = () => ["auto", ce, t], $ = () => [ce, t], B = () => ["", zn, mr], k = () => ["auto", vs, ce],
            M = () => ["bottom", "center", "left", "left-bottom", "left-top", "right", "right-bottom", "right-top", "top"],
            j = () => ["solid", "dashed", "dotted", "double", "none"],
            A = () => ["normal", "multiply", "screen", "overlay", "darken", "lighten", "color-dodge", "color-burn", "hard-light", "soft-light", "difference", "exclusion", "hue", "saturation", "color", "luminosity"],
            P = () => ["start", "end", "center", "between", "around", "evenly", "stretch"], _ = () => ["", "0", ce],
            R = () => ["auto", "avoid", "all", "avoid-page", "page", "left", "right", "column"], z = () => [vs, ce];
        return {
            cacheSize: 500,
            separator: ":",
            theme: {
                colors: [gi],
                spacing: [zn, mr],
                blur: ["none", "", gr, ce],
                brightness: z(),
                borderColor: [e],
                borderRadius: ["none", "", "full", gr, ce],
                borderSpacing: $(),
                borderWidth: B(),
                contrast: z(),
                grayscale: _(),
                hueRotate: z(),
                invert: _(),
                gap: $(),
                gradientColorStops: [e],
                gradientColorStopPositions: [CP, mr],
                inset: L(),
                margin: L(),
                opacity: z(),
                padding: $(),
                saturate: z(),
                scale: z(),
                sepia: _(),
                skew: z(),
                space: $(),
                translate: $()
            },
            classGroups: {
                aspect: [{aspect: ["auto", "square", "video", ce]}],
                container: ["container"],
                columns: [{columns: [gr]}],
                "break-after": [{"break-after": R()}],
                "break-before": [{"break-before": R()}],
                "break-inside": [{"break-inside": ["auto", "avoid", "avoid-page", "avoid-column"]}],
                "box-decoration": [{"box-decoration": ["slice", "clone"]}],
                box: [{box: ["border", "content"]}],
                display: ["block", "inline-block", "inline", "flex", "inline-flex", "table", "inline-table", "table-caption", "table-cell", "table-column", "table-column-group", "table-footer-group", "table-header-group", "table-row-group", "table-row", "flow-root", "grid", "inline-grid", "contents", "list-item", "hidden"],
                float: [{float: ["right", "left", "none", "start", "end"]}],
                clear: [{clear: ["left", "right", "both", "none", "start", "end"]}],
                isolation: ["isolate", "isolation-auto"],
                "object-fit": [{object: ["contain", "cover", "fill", "none", "scale-down"]}],
                "object-position": [{object: [...M(), ce]}],
                overflow: [{overflow: D()}],
                "overflow-x": [{"overflow-x": D()}],
                "overflow-y": [{"overflow-y": D()}],
                overscroll: [{overscroll: O()}],
                "overscroll-x": [{"overscroll-x": O()}],
                "overscroll-y": [{"overscroll-y": O()}],
                position: ["static", "fixed", "absolute", "relative", "sticky"],
                inset: [{inset: [m]}],
                "inset-x": [{"inset-x": [m]}],
                "inset-y": [{"inset-y": [m]}],
                start: [{start: [m]}],
                end: [{end: [m]}],
                top: [{top: [m]}],
                right: [{right: [m]}],
                bottom: [{bottom: [m]}],
                left: [{left: [m]}],
                visibility: ["visible", "invisible", "collapse"],
                z: [{z: ["auto", mi, ce]}],
                basis: [{basis: L()}],
                "flex-direction": [{flex: ["row", "row-reverse", "col", "col-reverse"]}],
                "flex-wrap": [{flex: ["wrap", "wrap-reverse", "nowrap"]}],
                flex: [{flex: ["1", "auto", "initial", "none", ce]}],
                grow: [{grow: _()}],
                shrink: [{shrink: _()}],
                order: [{order: ["first", "last", "none", mi, ce]}],
                "grid-cols": [{"grid-cols": [gi]}],
                "col-start-end": [{col: ["auto", {span: ["full", mi, ce]}, ce]}],
                "col-start": [{"col-start": k()}],
                "col-end": [{"col-end": k()}],
                "grid-rows": [{"grid-rows": [gi]}],
                "row-start-end": [{row: ["auto", {span: [mi, ce]}, ce]}],
                "row-start": [{"row-start": k()}],
                "row-end": [{"row-end": k()}],
                "grid-flow": [{"grid-flow": ["row", "col", "dense", "row-dense", "col-dense"]}],
                "auto-cols": [{"auto-cols": ["auto", "min", "max", "fr", ce]}],
                "auto-rows": [{"auto-rows": ["auto", "min", "max", "fr", ce]}],
                gap: [{gap: [p]}],
                "gap-x": [{"gap-x": [p]}],
                "gap-y": [{"gap-y": [p]}],
                "justify-content": [{justify: ["normal", ...P()]}],
                "justify-items": [{"justify-items": ["start", "end", "center", "stretch"]}],
                "justify-self": [{"justify-self": ["auto", "start", "end", "center", "stretch"]}],
                "align-content": [{content: ["normal", ...P(), "baseline"]}],
                "align-items": [{items: ["start", "end", "center", "baseline", "stretch"]}],
                "align-self": [{self: ["auto", "start", "end", "center", "stretch", "baseline"]}],
                "place-content": [{"place-content": [...P(), "baseline"]}],
                "place-items": [{"place-items": ["start", "end", "center", "baseline", "stretch"]}],
                "place-self": [{"place-self": ["auto", "start", "end", "center", "stretch"]}],
                p: [{p: [w]}],
                px: [{px: [w]}],
                py: [{py: [w]}],
                ps: [{ps: [w]}],
                pe: [{pe: [w]}],
                pt: [{pt: [w]}],
                pr: [{pr: [w]}],
                pb: [{pb: [w]}],
                pl: [{pl: [w]}],
                m: [{m: [b]}],
                mx: [{mx: [b]}],
                my: [{my: [b]}],
                ms: [{ms: [b]}],
                me: [{me: [b]}],
                mt: [{mt: [b]}],
                mr: [{mr: [b]}],
                mb: [{mb: [b]}],
                ml: [{ml: [b]}],
                "space-x": [{"space-x": [E]}],
                "space-x-reverse": ["space-x-reverse"],
                "space-y": [{"space-y": [E]}],
                "space-y-reverse": ["space-y-reverse"],
                w: [{w: ["auto", "min", "max", "fit", "svw", "lvw", "dvw", ce, t]}],
                "min-w": [{"min-w": [ce, t, "min", "max", "fit"]}],
                "max-w": [{"max-w": [ce, t, "none", "full", "min", "max", "fit", "prose", {screen: [gr]}, gr]}],
                h: [{h: [ce, t, "auto", "min", "max", "fit", "svh", "lvh", "dvh"]}],
                "min-h": [{"min-h": [ce, t, "min", "max", "fit", "svh", "lvh", "dvh"]}],
                "max-h": [{"max-h": [ce, t, "min", "max", "fit", "svh", "lvh", "dvh"]}],
                size: [{size: [ce, t, "auto", "min", "max", "fit"]}],
                "font-size": [{text: ["base", gr, mr]}],
                "font-smoothing": ["antialiased", "subpixel-antialiased"],
                "font-style": ["italic", "not-italic"],
                "font-weight": [{font: ["thin", "extralight", "light", "normal", "medium", "semibold", "bold", "extrabold", "black", $d]}],
                "font-family": [{font: [gi]}],
                "fvn-normal": ["normal-nums"],
                "fvn-ordinal": ["ordinal"],
                "fvn-slashed-zero": ["slashed-zero"],
                "fvn-figure": ["lining-nums", "oldstyle-nums"],
                "fvn-spacing": ["proportional-nums", "tabular-nums"],
                "fvn-fraction": ["diagonal-fractions", "stacked-fractions"],
                tracking: [{tracking: ["tighter", "tight", "normal", "wide", "wider", "widest", ce]}],
                "line-clamp": [{"line-clamp": ["none", vs, $d]}],
                leading: [{leading: ["none", "tight", "snug", "normal", "relaxed", "loose", zn, ce]}],
                "list-image": [{"list-image": ["none", ce]}],
                "list-style-type": [{list: ["none", "disc", "decimal", ce]}],
                "list-style-position": [{list: ["inside", "outside"]}],
                "placeholder-color": [{placeholder: [e]}],
                "placeholder-opacity": [{"placeholder-opacity": [y]}],
                "text-alignment": [{text: ["left", "center", "right", "justify", "start", "end"]}],
                "text-color": [{text: [e]}],
                "text-opacity": [{"text-opacity": [y]}],
                "text-decoration": ["underline", "overline", "line-through", "no-underline"],
                "text-decoration-style": [{decoration: [...j(), "wavy"]}],
                "text-decoration-thickness": [{decoration: ["auto", "from-font", zn, mr]}],
                "underline-offset": [{"underline-offset": ["auto", zn, ce]}],
                "text-decoration-color": [{decoration: [e]}],
                "text-transform": ["uppercase", "lowercase", "capitalize", "normal-case"],
                "text-overflow": ["truncate", "text-ellipsis", "text-clip"],
                "text-wrap": [{text: ["wrap", "nowrap", "balance", "pretty"]}],
                indent: [{indent: $()}],
                "vertical-align": [{align: ["baseline", "top", "middle", "bottom", "text-top", "text-bottom", "sub", "super", ce]}],
                whitespace: [{whitespace: ["normal", "nowrap", "pre", "pre-line", "pre-wrap", "break-spaces"]}],
                break: [{break: ["normal", "words", "all", "keep"]}],
                hyphens: [{hyphens: ["none", "manual", "auto"]}],
                content: [{content: ["none", ce]}],
                "bg-attachment": [{bg: ["fixed", "local", "scroll"]}],
                "bg-clip": [{"bg-clip": ["border", "padding", "content", "text"]}],
                "bg-opacity": [{"bg-opacity": [y]}],
                "bg-origin": [{"bg-origin": ["border", "padding", "content"]}],
                "bg-position": [{bg: [...M(), EP]}],
                "bg-repeat": [{bg: ["no-repeat", {repeat: ["", "x", "y", "round", "space"]}]}],
                "bg-size": [{bg: ["auto", "cover", "contain", NP]}],
                "bg-image": [{bg: ["none", {"gradient-to": ["t", "tr", "r", "br", "b", "bl", "l", "tl"]}, _P]}],
                "bg-color": [{bg: [e]}],
                "gradient-from-pos": [{from: [x]}],
                "gradient-via-pos": [{via: [x]}],
                "gradient-to-pos": [{to: [x]}],
                "gradient-from": [{from: [h]}],
                "gradient-via": [{via: [h]}],
                "gradient-to": [{to: [h]}],
                rounded: [{rounded: [s]}],
                "rounded-s": [{"rounded-s": [s]}],
                "rounded-e": [{"rounded-e": [s]}],
                "rounded-t": [{"rounded-t": [s]}],
                "rounded-r": [{"rounded-r": [s]}],
                "rounded-b": [{"rounded-b": [s]}],
                "rounded-l": [{"rounded-l": [s]}],
                "rounded-ss": [{"rounded-ss": [s]}],
                "rounded-se": [{"rounded-se": [s]}],
                "rounded-ee": [{"rounded-ee": [s]}],
                "rounded-es": [{"rounded-es": [s]}],
                "rounded-tl": [{"rounded-tl": [s]}],
                "rounded-tr": [{"rounded-tr": [s]}],
                "rounded-br": [{"rounded-br": [s]}],
                "rounded-bl": [{"rounded-bl": [s]}],
                "border-w": [{border: [a]}],
                "border-w-x": [{"border-x": [a]}],
                "border-w-y": [{"border-y": [a]}],
                "border-w-s": [{"border-s": [a]}],
                "border-w-e": [{"border-e": [a]}],
                "border-w-t": [{"border-t": [a]}],
                "border-w-r": [{"border-r": [a]}],
                "border-w-b": [{"border-b": [a]}],
                "border-w-l": [{"border-l": [a]}],
                "border-opacity": [{"border-opacity": [y]}],
                "border-style": [{border: [...j(), "hidden"]}],
                "divide-x": [{"divide-x": [a]}],
                "divide-x-reverse": ["divide-x-reverse"],
                "divide-y": [{"divide-y": [a]}],
                "divide-y-reverse": ["divide-y-reverse"],
                "divide-opacity": [{"divide-opacity": [y]}],
                "divide-style": [{divide: j()}],
                "border-color": [{border: [o]}],
                "border-color-x": [{"border-x": [o]}],
                "border-color-y": [{"border-y": [o]}],
                "border-color-s": [{"border-s": [o]}],
                "border-color-e": [{"border-e": [o]}],
                "border-color-t": [{"border-t": [o]}],
                "border-color-r": [{"border-r": [o]}],
                "border-color-b": [{"border-b": [o]}],
                "border-color-l": [{"border-l": [o]}],
                "divide-color": [{divide: [o]}],
                "outline-style": [{outline: ["", ...j()]}],
                "outline-offset": [{"outline-offset": [zn, ce]}],
                "outline-w": [{outline: [zn, mr]}],
                "outline-color": [{outline: [e]}],
                "ring-w": [{ring: B()}],
                "ring-w-inset": ["ring-inset"],
                "ring-color": [{ring: [e]}],
                "ring-opacity": [{"ring-opacity": [y]}],
                "ring-offset-w": [{"ring-offset": [zn, mr]}],
                "ring-offset-color": [{"ring-offset": [e]}],
                shadow: [{shadow: ["", "inner", "none", gr, jP]}],
                "shadow-color": [{shadow: [gi]}],
                opacity: [{opacity: [y]}],
                "mix-blend": [{"mix-blend": [...A(), "plus-lighter", "plus-darker"]}],
                "bg-blend": [{"bg-blend": A()}],
                filter: [{filter: ["", "none"]}],
                blur: [{blur: [n]}],
                brightness: [{brightness: [r]}],
                contrast: [{contrast: [l]}],
                "drop-shadow": [{"drop-shadow": ["", "none", gr, ce]}],
                grayscale: [{grayscale: [u]}],
                "hue-rotate": [{"hue-rotate": [d]}],
                invert: [{invert: [f]}],
                saturate: [{saturate: [v]}],
                sepia: [{sepia: [S]}],
                "backdrop-filter": [{"backdrop-filter": ["", "none"]}],
                "backdrop-blur": [{"backdrop-blur": [n]}],
                "backdrop-brightness": [{"backdrop-brightness": [r]}],
                "backdrop-contrast": [{"backdrop-contrast": [l]}],
                "backdrop-grayscale": [{"backdrop-grayscale": [u]}],
                "backdrop-hue-rotate": [{"backdrop-hue-rotate": [d]}],
                "backdrop-invert": [{"backdrop-invert": [f]}],
                "backdrop-opacity": [{"backdrop-opacity": [y]}],
                "backdrop-saturate": [{"backdrop-saturate": [v]}],
                "backdrop-sepia": [{"backdrop-sepia": [S]}],
                "border-collapse": [{border: ["collapse", "separate"]}],
                "border-spacing": [{"border-spacing": [i]}],
                "border-spacing-x": [{"border-spacing-x": [i]}],
                "border-spacing-y": [{"border-spacing-y": [i]}],
                "table-layout": [{table: ["auto", "fixed"]}],
                caption: [{caption: ["top", "bottom"]}],
                transition: [{transition: ["none", "all", "", "colors", "opacity", "shadow", "transform", ce]}],
                duration: [{duration: z()}],
                ease: [{ease: ["linear", "in", "out", "in-out", ce]}],
                delay: [{delay: z()}],
                animate: [{animate: ["none", "spin", "ping", "pulse", "bounce", ce]}],
                transform: [{transform: ["", "gpu", "none"]}],
                scale: [{scale: [C]}],
                "scale-x": [{"scale-x": [C]}],
                "scale-y": [{"scale-y": [C]}],
                rotate: [{rotate: [mi, ce]}],
                "translate-x": [{"translate-x": [T]}],
                "translate-y": [{"translate-y": [T]}],
                "skew-x": [{"skew-x": [N]}],
                "skew-y": [{"skew-y": [N]}],
                "transform-origin": [{origin: ["center", "top", "top-right", "right", "bottom-right", "bottom", "bottom-left", "left", "top-left", ce]}],
                accent: [{accent: ["auto", e]}],
                appearance: [{appearance: ["none", "auto"]}],
                cursor: [{cursor: ["auto", "default", "pointer", "wait", "text", "move", "help", "not-allowed", "none", "context-menu", "progress", "cell", "crosshair", "vertical-text", "alias", "copy", "no-drop", "grab", "grabbing", "all-scroll", "col-resize", "row-resize", "n-resize", "e-resize", "s-resize", "w-resize", "ne-resize", "nw-resize", "se-resize", "sw-resize", "ew-resize", "ns-resize", "nesw-resize", "nwse-resize", "zoom-in", "zoom-out", ce]}],
                "caret-color": [{caret: [e]}],
                "pointer-events": [{"pointer-events": ["none", "auto"]}],
                resize: [{resize: ["none", "y", "x", ""]}],
                "scroll-behavior": [{scroll: ["auto", "smooth"]}],
                "scroll-m": [{"scroll-m": $()}],
                "scroll-mx": [{"scroll-mx": $()}],
                "scroll-my": [{"scroll-my": $()}],
                "scroll-ms": [{"scroll-ms": $()}],
                "scroll-me": [{"scroll-me": $()}],
                "scroll-mt": [{"scroll-mt": $()}],
                "scroll-mr": [{"scroll-mr": $()}],
                "scroll-mb": [{"scroll-mb": $()}],
                "scroll-ml": [{"scroll-ml": $()}],
                "scroll-p": [{"scroll-p": $()}],
                "scroll-px": [{"scroll-px": $()}],
                "scroll-py": [{"scroll-py": $()}],
                "scroll-ps": [{"scroll-ps": $()}],
                "scroll-pe": [{"scroll-pe": $()}],
                "scroll-pt": [{"scroll-pt": $()}],
                "scroll-pr": [{"scroll-pr": $()}],
                "scroll-pb": [{"scroll-pb": $()}],
                "scroll-pl": [{"scroll-pl": $()}],
                "snap-align": [{snap: ["start", "end", "center", "align-none"]}],
                "snap-stop": [{snap: ["normal", "always"]}],
                "snap-type": [{snap: ["none", "x", "y", "both"]}],
                "snap-strictness": [{snap: ["mandatory", "proximity"]}],
                touch: [{touch: ["auto", "none", "manipulation"]}],
                "touch-x": [{"touch-pan": ["x", "left", "right"]}],
                "touch-y": [{"touch-pan": ["y", "up", "down"]}],
                "touch-pz": ["touch-pinch-zoom"],
                select: [{select: ["none", "text", "all", "auto"]}],
                "will-change": [{"will-change": ["auto", "scroll", "contents", "transform", ce]}],
                fill: [{fill: [e, "none"]}],
                "stroke-w": [{stroke: [zn, mr, $d]}],
                stroke: [{stroke: [e, "none"]}],
                sr: ["sr-only", "not-sr-only"],
                "forced-color-adjust": [{"forced-color-adjust": ["auto", "none"]}]
            },
            conflictingClassGroups: {
                overflow: ["overflow-x", "overflow-y"],
                overscroll: ["overscroll-x", "overscroll-y"],
                inset: ["inset-x", "inset-y", "start", "end", "top", "right", "bottom", "left"],
                "inset-x": ["right", "left"],
                "inset-y": ["top", "bottom"],
                flex: ["basis", "grow", "shrink"],
                gap: ["gap-x", "gap-y"],
                p: ["px", "py", "ps", "pe", "pt", "pr", "pb", "pl"],
                px: ["pr", "pl"],
                py: ["pt", "pb"],
                m: ["mx", "my", "ms", "me", "mt", "mr", "mb", "ml"],
                mx: ["mr", "ml"],
                my: ["mt", "mb"],
                size: ["w", "h"],
                "font-size": ["leading"],
                "fvn-normal": ["fvn-ordinal", "fvn-slashed-zero", "fvn-figure", "fvn-spacing", "fvn-fraction"],
                "fvn-ordinal": ["fvn-normal"],
                "fvn-slashed-zero": ["fvn-normal"],
                "fvn-figure": ["fvn-normal"],
                "fvn-spacing": ["fvn-normal"],
                "fvn-fraction": ["fvn-normal"],
                "line-clamp": ["display", "overflow"],
                rounded: ["rounded-s", "rounded-e", "rounded-t", "rounded-r", "rounded-b", "rounded-l", "rounded-ss", "rounded-se", "rounded-ee", "rounded-es", "rounded-tl", "rounded-tr", "rounded-br", "rounded-bl"],
                "rounded-s": ["rounded-ss", "rounded-es"],
                "rounded-e": ["rounded-se", "rounded-ee"],
                "rounded-t": ["rounded-tl", "rounded-tr"],
                "rounded-r": ["rounded-tr", "rounded-br"],
                "rounded-b": ["rounded-br", "rounded-bl"],
                "rounded-l": ["rounded-tl", "rounded-bl"],
                "border-spacing": ["border-spacing-x", "border-spacing-y"],
                "border-w": ["border-w-s", "border-w-e", "border-w-t", "border-w-r", "border-w-b", "border-w-l"],
                "border-w-x": ["border-w-r", "border-w-l"],
                "border-w-y": ["border-w-t", "border-w-b"],
                "border-color": ["border-color-s", "border-color-e", "border-color-t", "border-color-r", "border-color-b", "border-color-l"],
                "border-color-x": ["border-color-r", "border-color-l"],
                "border-color-y": ["border-color-t", "border-color-b"],
                "scroll-m": ["scroll-mx", "scroll-my", "scroll-ms", "scroll-me", "scroll-mt", "scroll-mr", "scroll-mb", "scroll-ml"],
                "scroll-mx": ["scroll-mr", "scroll-ml"],
                "scroll-my": ["scroll-mt", "scroll-mb"],
                "scroll-p": ["scroll-px", "scroll-py", "scroll-ps", "scroll-pe", "scroll-pt", "scroll-pr", "scroll-pb", "scroll-pl"],
                "scroll-px": ["scroll-pr", "scroll-pl"],
                "scroll-py": ["scroll-pt", "scroll-pb"],
                touch: ["touch-x", "touch-y", "touch-pz"],
                "touch-x": ["touch"],
                "touch-y": ["touch"],
                "touch-pz": ["touch"]
            },
            conflictingClassGroupModifiers: {"font-size": ["leading"]}
        }
    }, AP = hP(RP);

function se(...e) {
    return AP(Xw(e))
}

const IP = $j, f1 = g.forwardRef(({className: e, ...t}, n) => c.jsx(Vw, {
    ref: n,
    className: se("fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]", e), ...t
}));
f1.displayName = Vw.displayName;
const OP = mu("group pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-md border p-6 pr-8 shadow-lg transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--radix-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--radix-toast-swipe-move-x)] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full data-[state=open]:sm:slide-in-from-bottom-full", {
    variants: {
        variant: {
            default: "border bg-background text-foreground",
            destructive: "destructive group border-destructive bg-destructive text-destructive-foreground"
        }
    }, defaultVariants: {variant: "default"}
}), p1 = g.forwardRef(({className: e, variant: t, ...n}, r) => c.jsx(Hw, {
    ref: r,
    className: se(OP({variant: t}), e), ...n
}));
p1.displayName = Hw.displayName;
const DP = g.forwardRef(({className: e, ...t}, n) => c.jsx(Kw, {
    ref: n,
    className: se("inline-flex h-8 shrink-0 items-center justify-center rounded-md border bg-transparent px-3 text-sm font-medium ring-offset-background transition-colors hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 group-[.destructive]:border-muted/40 group-[.destructive]:hover:border-destructive/30 group-[.destructive]:hover:bg-destructive group-[.destructive]:hover:text-destructive-foreground group-[.destructive]:focus:ring-destructive", e), ...t
}));
DP.displayName = Kw.displayName;
const h1 = g.forwardRef(({className: e, ...t}, n) => c.jsx(Yw, {
    ref: n,
    className: se("absolute right-2 top-2 rounded-md p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100 group-[.destructive]:text-red-300 group-[.destructive]:hover:text-red-50 group-[.destructive]:focus:ring-red-400 group-[.destructive]:focus:ring-offset-red-600", e),
    "toast-close": "", ...t,
    children: c.jsx(Zs, {className: "h-4 w-4"})
}));
h1.displayName = Yw.displayName;
const m1 = g.forwardRef(({className: e, ...t}, n) => c.jsx(Uw, {
    ref: n,
    className: se("text-sm font-semibold", e), ...t
}));
m1.displayName = Uw.displayName;
const g1 = g.forwardRef(({className: e, ...t}, n) => c.jsx(Ww, {ref: n, className: se("text-sm opacity-90", e), ...t}));
g1.displayName = Ww.displayName;

function $P() {
    const {toasts: e} = Oh();
    return c.jsxs(IP, {
        children: [e.map(function ({id: t, title: n, description: r, action: o, ...s}) {
            return c.jsxs(p1, {
                ...s,
                children: [c.jsxs("div", {
                    className: "grid gap-1",
                    children: [n && c.jsx(m1, {children: n}), r && c.jsx(g1, {children: r})]
                }), o, c.jsx(h1, {})]
            }, t)
        }), c.jsx(f1, {})]
    })
}

var hv = ["light", "dark"], LP = "(prefers-color-scheme: dark)", FP = g.createContext(void 0), zP = {
    setTheme: e => {
    }, themes: []
}, BP = () => {
    var e;
    return (e = g.useContext(FP)) != null ? e : zP
};
g.memo(({
            forcedTheme: e,
            storageKey: t,
            attribute: n,
            enableSystem: r,
            enableColorScheme: o,
            defaultTheme: s,
            value: i,
            attrs: a,
            nonce: l
        }) => {
    let u = s === "system",
        d = n === "class" ? `var d=document.documentElement,c=d.classList;${`c.remove(${a.map(x => `'${x}'`).join(",")})`};` : `var d=document.documentElement,n='${n}',s='setAttribute';`,
        f = o ? hv.includes(s) && s ? `if(e==='light'||e==='dark'||!e)d.style.colorScheme=e||'${s}'` : "if(e==='light'||e==='dark')d.style.colorScheme=e" : "",
        p = (x, m = !1, b = !0) => {
            let y = i ? i[x] : x, w = m ? x + "|| ''" : `'${y}'`, v = "";
            return o && b && !m && hv.includes(x) && (v += `d.style.colorScheme = '${x}';`), n === "class" ? m || y ? v += `c.add(${w})` : v += "null" : y && (v += `d[s](n,${w})`), v
        },
        h = e ? `!function(){${d}${p(e)}}()` : r ? `!function(){try{${d}var e=localStorage.getItem('${t}');if('system'===e||(!e&&${u})){var t='${LP}',m=window.matchMedia(t);if(m.media!==t||m.matches){${p("dark")}}else{${p("light")}}}else if(e){${i ? `var x=${JSON.stringify(i)};` : ""}${p(i ? "x[e]" : "e", !0)}}${u ? "" : "else{" + p(s, !1, !1) + "}"}${f}}catch(e){}}()` : `!function(){try{${d}var e=localStorage.getItem('${t}');if(e){${i ? `var x=${JSON.stringify(i)};` : ""}${p(i ? "x[e]" : "e", !0)}}else{${p(s, !1, !1)};}${f}}catch(t){}}();`;
    return g.createElement("script", {nonce: l, dangerouslySetInnerHTML: {__html: h}})
});
var VP = e => {
        switch (e) {
            case"success":
                return WP;
            case"info":
                return YP;
            case"warning":
                return KP;
            case"error":
                return GP;
            default:
                return null
        }
    }, HP = Array(12).fill(0), UP = ({
                                         visible: e,
                                         className: t
                                     }) => Z.createElement("div", {
        className: ["sonner-loading-wrapper", t].filter(Boolean).join(" "),
        "data-visible": e
    }, Z.createElement("div", {className: "sonner-spinner"}, HP.map((n, r) => Z.createElement("div", {
        className: "sonner-loading-bar",
        key: `spinner-bar-${r}`
    })))), WP = Z.createElement("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 20 20",
        fill: "currentColor",
        height: "20",
        width: "20"
    }, Z.createElement("path", {
        fillRule: "evenodd",
        d: "M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z",
        clipRule: "evenodd"
    })), KP = Z.createElement("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 24 24",
        fill: "currentColor",
        height: "20",
        width: "20"
    }, Z.createElement("path", {
        fillRule: "evenodd",
        d: "M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z",
        clipRule: "evenodd"
    })), YP = Z.createElement("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 20 20",
        fill: "currentColor",
        height: "20",
        width: "20"
    }, Z.createElement("path", {
        fillRule: "evenodd",
        d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z",
        clipRule: "evenodd"
    })), GP = Z.createElement("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 20 20",
        fill: "currentColor",
        height: "20",
        width: "20"
    }, Z.createElement("path", {
        fillRule: "evenodd",
        d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z",
        clipRule: "evenodd"
    })), XP = Z.createElement("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        width: "12",
        height: "12",
        viewBox: "0 0 24 24",
        fill: "none",
        stroke: "currentColor",
        strokeWidth: "1.5",
        strokeLinecap: "round",
        strokeLinejoin: "round"
    }, Z.createElement("line", {x1: "18", y1: "6", x2: "6", y2: "18"}), Z.createElement("line", {
        x1: "6",
        y1: "6",
        x2: "18",
        y2: "18"
    })), qP = () => {
        let [e, t] = Z.useState(document.hidden);
        return Z.useEffect(() => {
            let n = () => {
                t(document.hidden)
            };
            return document.addEventListener("visibilitychange", n), () => window.removeEventListener("visibilitychange", n)
        }, []), e
    }, ap = 1, QP = class {
        constructor() {
            this.subscribe = e => (this.subscribers.push(e), () => {
                let t = this.subscribers.indexOf(e);
                this.subscribers.splice(t, 1)
            }), this.publish = e => {
                this.subscribers.forEach(t => t(e))
            }, this.addToast = e => {
                this.publish(e), this.toasts = [...this.toasts, e]
            }, this.create = e => {
                var t;
                let {message: n, ...r} = e,
                    o = typeof (e == null ? void 0 : e.id) == "number" || ((t = e.id) == null ? void 0 : t.length) > 0 ? e.id : ap++,
                    s = this.toasts.find(a => a.id === o), i = e.dismissible === void 0 ? !0 : e.dismissible;
                return this.dismissedToasts.has(o) && this.dismissedToasts.delete(o), s ? this.toasts = this.toasts.map(a => a.id === o ? (this.publish({
                    ...a, ...e,
                    id: o,
                    title: n
                }), {...a, ...e, id: o, dismissible: i, title: n}) : a) : this.addToast({
                    title: n, ...r,
                    dismissible: i,
                    id: o
                }), o
            }, this.dismiss = e => (this.dismissedToasts.add(e), e || this.toasts.forEach(t => {
                this.subscribers.forEach(n => n({id: t.id, dismiss: !0}))
            }), this.subscribers.forEach(t => t({id: e, dismiss: !0})), e), this.message = (e, t) => this.create({
                ...t,
                message: e
            }), this.error = (e, t) => this.create({
                ...t,
                message: e,
                type: "error"
            }), this.success = (e, t) => this.create({
                ...t,
                type: "success",
                message: e
            }), this.info = (e, t) => this.create({
                ...t,
                type: "info",
                message: e
            }), this.warning = (e, t) => this.create({
                ...t,
                type: "warning",
                message: e
            }), this.loading = (e, t) => this.create({...t, type: "loading", message: e}), this.promise = (e, t) => {
                if (!t) return;
                let n;
                t.loading !== void 0 && (n = this.create({
                    ...t,
                    promise: e,
                    type: "loading",
                    message: t.loading,
                    description: typeof t.description != "function" ? t.description : void 0
                }));
                let r = e instanceof Promise ? e : e(), o = n !== void 0, s, i = r.then(async l => {
                    if (s = ["resolve", l], Z.isValidElement(l)) o = !1, this.create({
                        id: n,
                        type: "default",
                        message: l
                    }); else if (JP(l) && !l.ok) {
                        o = !1;
                        let u = typeof t.error == "function" ? await t.error(`HTTP error! status: ${l.status}`) : t.error,
                            d = typeof t.description == "function" ? await t.description(`HTTP error! status: ${l.status}`) : t.description;
                        this.create({id: n, type: "error", message: u, description: d})
                    } else if (t.success !== void 0) {
                        o = !1;
                        let u = typeof t.success == "function" ? await t.success(l) : t.success,
                            d = typeof t.description == "function" ? await t.description(l) : t.description;
                        this.create({id: n, type: "success", message: u, description: d})
                    }
                }).catch(async l => {
                    if (s = ["reject", l], t.error !== void 0) {
                        o = !1;
                        let u = typeof t.error == "function" ? await t.error(l) : t.error,
                            d = typeof t.description == "function" ? await t.description(l) : t.description;
                        this.create({id: n, type: "error", message: u, description: d})
                    }
                }).finally(() => {
                    var l;
                    o && (this.dismiss(n), n = void 0), (l = t.finally) == null || l.call(t)
                }), a = () => new Promise((l, u) => i.then(() => s[0] === "reject" ? u(s[1]) : l(s[1])).catch(u));
                return typeof n != "string" && typeof n != "number" ? {unwrap: a} : Object.assign(n, {unwrap: a})
            }, this.custom = (e, t) => {
                let n = (t == null ? void 0 : t.id) || ap++;
                return this.create({jsx: e(n), id: n, ...t}), n
            }, this.getActiveToasts = () => this.toasts.filter(e => !this.dismissedToasts.has(e.id)), this.subscribers = [], this.toasts = [], this.dismissedToasts = new Set
        }
    }, ht = new QP, ZP = (e, t) => {
        let n = (t == null ? void 0 : t.id) || ap++;
        return ht.addToast({title: e, ...t, id: n}), n
    },
    JP = e => e && typeof e == "object" && "ok" in e && typeof e.ok == "boolean" && "status" in e && typeof e.status == "number",
    eT = ZP, tT = () => ht.toasts, nT = () => ht.getActiveToasts();
Object.assign(eT, {
    success: ht.success,
    info: ht.info,
    warning: ht.warning,
    error: ht.error,
    custom: ht.custom,
    message: ht.message,
    promise: ht.promise,
    dismiss: ht.dismiss,
    loading: ht.loading
}, {getHistory: tT, getToasts: nT});

function rT(e, {insertAt: t} = {}) {
    if (typeof document > "u") return;
    let n = document.head || document.getElementsByTagName("head")[0], r = document.createElement("style");
    r.type = "text/css", t === "top" && n.firstChild ? n.insertBefore(r, n.firstChild) : n.appendChild(r), r.styleSheet ? r.styleSheet.cssText = e : r.appendChild(document.createTextNode(e))
}

rT(`:where(html[dir="ltr"]),:where([data-sonner-toaster][dir="ltr"]){--toast-icon-margin-start: -3px;--toast-icon-margin-end: 4px;--toast-svg-margin-start: -1px;--toast-svg-margin-end: 0px;--toast-button-margin-start: auto;--toast-button-margin-end: 0;--toast-close-button-start: 0;--toast-close-button-end: unset;--toast-close-button-transform: translate(-35%, -35%)}:where(html[dir="rtl"]),:where([data-sonner-toaster][dir="rtl"]){--toast-icon-margin-start: 4px;--toast-icon-margin-end: -3px;--toast-svg-margin-start: 0px;--toast-svg-margin-end: -1px;--toast-button-margin-start: 0;--toast-button-margin-end: auto;--toast-close-button-start: unset;--toast-close-button-end: 0;--toast-close-button-transform: translate(35%, -35%)}:where([data-sonner-toaster]){position:fixed;width:var(--width);font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;--gray1: hsl(0, 0%, 99%);--gray2: hsl(0, 0%, 97.3%);--gray3: hsl(0, 0%, 95.1%);--gray4: hsl(0, 0%, 93%);--gray5: hsl(0, 0%, 90.9%);--gray6: hsl(0, 0%, 88.7%);--gray7: hsl(0, 0%, 85.8%);--gray8: hsl(0, 0%, 78%);--gray9: hsl(0, 0%, 56.1%);--gray10: hsl(0, 0%, 52.3%);--gray11: hsl(0, 0%, 43.5%);--gray12: hsl(0, 0%, 9%);--border-radius: 8px;box-sizing:border-box;padding:0;margin:0;list-style:none;outline:none;z-index:999999999;transition:transform .4s ease}:where([data-sonner-toaster][data-lifted="true"]){transform:translateY(-10px)}@media (hover: none) and (pointer: coarse){:where([data-sonner-toaster][data-lifted="true"]){transform:none}}:where([data-sonner-toaster][data-x-position="right"]){right:var(--offset-right)}:where([data-sonner-toaster][data-x-position="left"]){left:var(--offset-left)}:where([data-sonner-toaster][data-x-position="center"]){left:50%;transform:translate(-50%)}:where([data-sonner-toaster][data-y-position="top"]){top:var(--offset-top)}:where([data-sonner-toaster][data-y-position="bottom"]){bottom:var(--offset-bottom)}:where([data-sonner-toast]){--y: translateY(100%);--lift-amount: calc(var(--lift) * var(--gap));z-index:var(--z-index);position:absolute;opacity:0;transform:var(--y);filter:blur(0);touch-action:none;transition:transform .4s,opacity .4s,height .4s,box-shadow .2s;box-sizing:border-box;outline:none;overflow-wrap:anywhere}:where([data-sonner-toast][data-styled="true"]){padding:16px;background:var(--normal-bg);border:1px solid var(--normal-border);color:var(--normal-text);border-radius:var(--border-radius);box-shadow:0 4px 12px #0000001a;width:var(--width);font-size:13px;display:flex;align-items:center;gap:6px}:where([data-sonner-toast]:focus-visible){box-shadow:0 4px 12px #0000001a,0 0 0 2px #0003}:where([data-sonner-toast][data-y-position="top"]){top:0;--y: translateY(-100%);--lift: 1;--lift-amount: calc(1 * var(--gap))}:where([data-sonner-toast][data-y-position="bottom"]){bottom:0;--y: translateY(100%);--lift: -1;--lift-amount: calc(var(--lift) * var(--gap))}:where([data-sonner-toast]) :where([data-description]){font-weight:400;line-height:1.4;color:inherit}:where([data-sonner-toast]) :where([data-title]){font-weight:500;line-height:1.5;color:inherit}:where([data-sonner-toast]) :where([data-icon]){display:flex;height:16px;width:16px;position:relative;justify-content:flex-start;align-items:center;flex-shrink:0;margin-left:var(--toast-icon-margin-start);margin-right:var(--toast-icon-margin-end)}:where([data-sonner-toast][data-promise="true"]) :where([data-icon])>svg{opacity:0;transform:scale(.8);transform-origin:center;animation:sonner-fade-in .3s ease forwards}:where([data-sonner-toast]) :where([data-icon])>*{flex-shrink:0}:where([data-sonner-toast]) :where([data-icon]) svg{margin-left:var(--toast-svg-margin-start);margin-right:var(--toast-svg-margin-end)}:where([data-sonner-toast]) :where([data-content]){display:flex;flex-direction:column;gap:2px}[data-sonner-toast][data-styled=true] [data-button]{border-radius:4px;padding-left:8px;padding-right:8px;height:24px;font-size:12px;color:var(--normal-bg);background:var(--normal-text);margin-left:var(--toast-button-margin-start);margin-right:var(--toast-button-margin-end);border:none;cursor:pointer;outline:none;display:flex;align-items:center;flex-shrink:0;transition:opacity .4s,box-shadow .2s}:where([data-sonner-toast]) :where([data-button]):focus-visible{box-shadow:0 0 0 2px #0006}:where([data-sonner-toast]) :where([data-button]):first-of-type{margin-left:var(--toast-button-margin-start);margin-right:var(--toast-button-margin-end)}:where([data-sonner-toast]) :where([data-cancel]){color:var(--normal-text);background:rgba(0,0,0,.08)}:where([data-sonner-toast][data-theme="dark"]) :where([data-cancel]){background:rgba(255,255,255,.3)}:where([data-sonner-toast]) :where([data-close-button]){position:absolute;left:var(--toast-close-button-start);right:var(--toast-close-button-end);top:0;height:20px;width:20px;display:flex;justify-content:center;align-items:center;padding:0;color:var(--gray12);border:1px solid var(--gray4);transform:var(--toast-close-button-transform);border-radius:50%;cursor:pointer;z-index:1;transition:opacity .1s,background .2s,border-color .2s}[data-sonner-toast] [data-close-button]{background:var(--gray1)}:where([data-sonner-toast]) :where([data-close-button]):focus-visible{box-shadow:0 4px 12px #0000001a,0 0 0 2px #0003}:where([data-sonner-toast]) :where([data-disabled="true"]){cursor:not-allowed}:where([data-sonner-toast]):hover :where([data-close-button]):hover{background:var(--gray2);border-color:var(--gray5)}:where([data-sonner-toast][data-swiping="true"]):before{content:"";position:absolute;left:-50%;right:-50%;height:100%;z-index:-1}:where([data-sonner-toast][data-y-position="top"][data-swiping="true"]):before{bottom:50%;transform:scaleY(3) translateY(50%)}:where([data-sonner-toast][data-y-position="bottom"][data-swiping="true"]):before{top:50%;transform:scaleY(3) translateY(-50%)}:where([data-sonner-toast][data-swiping="false"][data-removed="true"]):before{content:"";position:absolute;inset:0;transform:scaleY(2)}:where([data-sonner-toast]):after{content:"";position:absolute;left:0;height:calc(var(--gap) + 1px);bottom:100%;width:100%}:where([data-sonner-toast][data-mounted="true"]){--y: translateY(0);opacity:1}:where([data-sonner-toast][data-expanded="false"][data-front="false"]){--scale: var(--toasts-before) * .05 + 1;--y: translateY(calc(var(--lift-amount) * var(--toasts-before))) scale(calc(-1 * var(--scale)));height:var(--front-toast-height)}:where([data-sonner-toast])>*{transition:opacity .4s}:where([data-sonner-toast][data-expanded="false"][data-front="false"][data-styled="true"])>*{opacity:0}:where([data-sonner-toast][data-visible="false"]){opacity:0;pointer-events:none}:where([data-sonner-toast][data-mounted="true"][data-expanded="true"]){--y: translateY(calc(var(--lift) * var(--offset)));height:var(--initial-height)}:where([data-sonner-toast][data-removed="true"][data-front="true"][data-swipe-out="false"]){--y: translateY(calc(var(--lift) * -100%));opacity:0}:where([data-sonner-toast][data-removed="true"][data-front="false"][data-swipe-out="false"][data-expanded="true"]){--y: translateY(calc(var(--lift) * var(--offset) + var(--lift) * -100%));opacity:0}:where([data-sonner-toast][data-removed="true"][data-front="false"][data-swipe-out="false"][data-expanded="false"]){--y: translateY(40%);opacity:0;transition:transform .5s,opacity .2s}:where([data-sonner-toast][data-removed="true"][data-front="false"]):before{height:calc(var(--initial-height) + 20%)}[data-sonner-toast][data-swiping=true]{transform:var(--y) translateY(var(--swipe-amount-y, 0px)) translate(var(--swipe-amount-x, 0px));transition:none}[data-sonner-toast][data-swiped=true]{user-select:none}[data-sonner-toast][data-swipe-out=true][data-y-position=bottom],[data-sonner-toast][data-swipe-out=true][data-y-position=top]{animation-duration:.2s;animation-timing-function:ease-out;animation-fill-mode:forwards}[data-sonner-toast][data-swipe-out=true][data-swipe-direction=left]{animation-name:swipe-out-left}[data-sonner-toast][data-swipe-out=true][data-swipe-direction=right]{animation-name:swipe-out-right}[data-sonner-toast][data-swipe-out=true][data-swipe-direction=up]{animation-name:swipe-out-up}[data-sonner-toast][data-swipe-out=true][data-swipe-direction=down]{animation-name:swipe-out-down}@keyframes swipe-out-left{0%{transform:var(--y) translate(var(--swipe-amount-x));opacity:1}to{transform:var(--y) translate(calc(var(--swipe-amount-x) - 100%));opacity:0}}@keyframes swipe-out-right{0%{transform:var(--y) translate(var(--swipe-amount-x));opacity:1}to{transform:var(--y) translate(calc(var(--swipe-amount-x) + 100%));opacity:0}}@keyframes swipe-out-up{0%{transform:var(--y) translateY(var(--swipe-amount-y));opacity:1}to{transform:var(--y) translateY(calc(var(--swipe-amount-y) - 100%));opacity:0}}@keyframes swipe-out-down{0%{transform:var(--y) translateY(var(--swipe-amount-y));opacity:1}to{transform:var(--y) translateY(calc(var(--swipe-amount-y) + 100%));opacity:0}}@media (max-width: 600px){[data-sonner-toaster]{position:fixed;right:var(--mobile-offset-right);left:var(--mobile-offset-left);width:100%}[data-sonner-toaster][dir=rtl]{left:calc(var(--mobile-offset-left) * -1)}[data-sonner-toaster] [data-sonner-toast]{left:0;right:0;width:calc(100% - var(--mobile-offset-left) * 2)}[data-sonner-toaster][data-x-position=left]{left:var(--mobile-offset-left)}[data-sonner-toaster][data-y-position=bottom]{bottom:var(--mobile-offset-bottom)}[data-sonner-toaster][data-y-position=top]{top:var(--mobile-offset-top)}[data-sonner-toaster][data-x-position=center]{left:var(--mobile-offset-left);right:var(--mobile-offset-right);transform:none}}[data-sonner-toaster][data-theme=light]{--normal-bg: #fff;--normal-border: var(--gray4);--normal-text: var(--gray12);--success-bg: hsl(143, 85%, 96%);--success-border: hsl(145, 92%, 91%);--success-text: hsl(140, 100%, 27%);--info-bg: hsl(208, 100%, 97%);--info-border: hsl(221, 91%, 91%);--info-text: hsl(210, 92%, 45%);--warning-bg: hsl(49, 100%, 97%);--warning-border: hsl(49, 91%, 91%);--warning-text: hsl(31, 92%, 45%);--error-bg: hsl(359, 100%, 97%);--error-border: hsl(359, 100%, 94%);--error-text: hsl(360, 100%, 45%)}[data-sonner-toaster][data-theme=light] [data-sonner-toast][data-invert=true]{--normal-bg: #000;--normal-border: hsl(0, 0%, 20%);--normal-text: var(--gray1)}[data-sonner-toaster][data-theme=dark] [data-sonner-toast][data-invert=true]{--normal-bg: #fff;--normal-border: var(--gray3);--normal-text: var(--gray12)}[data-sonner-toaster][data-theme=dark]{--normal-bg: #000;--normal-bg-hover: hsl(0, 0%, 12%);--normal-border: hsl(0, 0%, 20%);--normal-border-hover: hsl(0, 0%, 25%);--normal-text: var(--gray1);--success-bg: hsl(150, 100%, 6%);--success-border: hsl(147, 100%, 12%);--success-text: hsl(150, 86%, 65%);--info-bg: hsl(215, 100%, 6%);--info-border: hsl(223, 100%, 12%);--info-text: hsl(216, 87%, 65%);--warning-bg: hsl(64, 100%, 6%);--warning-border: hsl(60, 100%, 12%);--warning-text: hsl(46, 87%, 65%);--error-bg: hsl(358, 76%, 10%);--error-border: hsl(357, 89%, 16%);--error-text: hsl(358, 100%, 81%)}[data-sonner-toaster][data-theme=dark] [data-sonner-toast] [data-close-button]{background:var(--normal-bg);border-color:var(--normal-border);color:var(--normal-text)}[data-sonner-toaster][data-theme=dark] [data-sonner-toast] [data-close-button]:hover{background:var(--normal-bg-hover);border-color:var(--normal-border-hover)}[data-rich-colors=true][data-sonner-toast][data-type=success],[data-rich-colors=true][data-sonner-toast][data-type=success] [data-close-button]{background:var(--success-bg);border-color:var(--success-border);color:var(--success-text)}[data-rich-colors=true][data-sonner-toast][data-type=info],[data-rich-colors=true][data-sonner-toast][data-type=info] [data-close-button]{background:var(--info-bg);border-color:var(--info-border);color:var(--info-text)}[data-rich-colors=true][data-sonner-toast][data-type=warning],[data-rich-colors=true][data-sonner-toast][data-type=warning] [data-close-button]{background:var(--warning-bg);border-color:var(--warning-border);color:var(--warning-text)}[data-rich-colors=true][data-sonner-toast][data-type=error],[data-rich-colors=true][data-sonner-toast][data-type=error] [data-close-button]{background:var(--error-bg);border-color:var(--error-border);color:var(--error-text)}.sonner-loading-wrapper{--size: 16px;height:var(--size);width:var(--size);position:absolute;inset:0;z-index:10}.sonner-loading-wrapper[data-visible=false]{transform-origin:center;animation:sonner-fade-out .2s ease forwards}.sonner-spinner{position:relative;top:50%;left:50%;height:var(--size);width:var(--size)}.sonner-loading-bar{animation:sonner-spin 1.2s linear infinite;background:var(--gray11);border-radius:6px;height:8%;left:-10%;position:absolute;top:-3.9%;width:24%}.sonner-loading-bar:nth-child(1){animation-delay:-1.2s;transform:rotate(.0001deg) translate(146%)}.sonner-loading-bar:nth-child(2){animation-delay:-1.1s;transform:rotate(30deg) translate(146%)}.sonner-loading-bar:nth-child(3){animation-delay:-1s;transform:rotate(60deg) translate(146%)}.sonner-loading-bar:nth-child(4){animation-delay:-.9s;transform:rotate(90deg) translate(146%)}.sonner-loading-bar:nth-child(5){animation-delay:-.8s;transform:rotate(120deg) translate(146%)}.sonner-loading-bar:nth-child(6){animation-delay:-.7s;transform:rotate(150deg) translate(146%)}.sonner-loading-bar:nth-child(7){animation-delay:-.6s;transform:rotate(180deg) translate(146%)}.sonner-loading-bar:nth-child(8){animation-delay:-.5s;transform:rotate(210deg) translate(146%)}.sonner-loading-bar:nth-child(9){animation-delay:-.4s;transform:rotate(240deg) translate(146%)}.sonner-loading-bar:nth-child(10){animation-delay:-.3s;transform:rotate(270deg) translate(146%)}.sonner-loading-bar:nth-child(11){animation-delay:-.2s;transform:rotate(300deg) translate(146%)}.sonner-loading-bar:nth-child(12){animation-delay:-.1s;transform:rotate(330deg) translate(146%)}@keyframes sonner-fade-in{0%{opacity:0;transform:scale(.8)}to{opacity:1;transform:scale(1)}}@keyframes sonner-fade-out{0%{opacity:1;transform:scale(1)}to{opacity:0;transform:scale(.8)}}@keyframes sonner-spin{0%{opacity:1}to{opacity:.15}}@media (prefers-reduced-motion){[data-sonner-toast],[data-sonner-toast]>*,.sonner-loading-bar{transition:none!important;animation:none!important}}.sonner-loader{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);transform-origin:center;transition:opacity .2s,transform .2s}.sonner-loader[data-visible=false]{opacity:0;transform:scale(.8) translate(-50%,-50%)}
`);

function wl(e) {
    return e.label !== void 0
}

var oT = 3, sT = "32px", iT = "16px", mv = 4e3, aT = 356, lT = 14, cT = 20, uT = 200;

function qt(...e) {
    return e.filter(Boolean).join(" ")
}

function dT(e) {
    let [t, n] = e.split("-"), r = [];
    return t && r.push(t), n && r.push(n), r
}

var fT = e => {
    var t, n, r, o, s, i, a, l, u, d, f;
    let {
            invert: p,
            toast: h,
            unstyled: x,
            interacting: m,
            setHeights: b,
            visibleToasts: y,
            heights: w,
            index: v,
            toasts: C,
            expanded: S,
            removeToast: N,
            defaultRichColors: E,
            closeButton: T,
            style: O,
            cancelButtonStyle: D,
            actionButtonStyle: L,
            className: $ = "",
            descriptionClassName: B = "",
            duration: k,
            position: M,
            gap: j,
            loadingIcon: A,
            expandByDefault: P,
            classNames: _,
            icons: R,
            closeButtonAriaLabel: z = "Close toast",
            pauseWhenPageIsHidden: I
        } = e, [V, H] = Z.useState(null), [U, K] = Z.useState(null), [F, Y] = Z.useState(!1), [X, te] = Z.useState(!1), [ee, Q] = Z.useState(!1), [ue, ie] = Z.useState(!1), [we, Ne] = Z.useState(!1), [pt, $n] = Z.useState(0), [yn, cr] = Z.useState(0),
        Ln = Z.useRef(h.duration || k || mv), ur = Z.useRef(null), Ct = Z.useRef(null), ri = v === 0, dr = v + 1 <= y,
        Be = h.type, Gt = h.dismissible !== !1, td = h.className || "", oi = h.descriptionClassName || "",
        Ho = Z.useMemo(() => w.findIndex(oe => oe.toastId === h.id) || 0, [w, h.id]), nd = Z.useMemo(() => {
            var oe;
            return (oe = h.closeButton) != null ? oe : T
        }, [h.closeButton, T]), Ga = Z.useMemo(() => h.duration || k || mv, [h.duration, k]), si = Z.useRef(0),
        fr = Z.useRef(0), Xa = Z.useRef(0), pr = Z.useRef(null), [rd, od] = M.split("-"),
        qa = Z.useMemo(() => w.reduce((oe, ye, be) => be >= Ho ? oe : oe + ye.height, 0), [w, Ho]), Qa = qP(),
        sd = h.invert || p, ii = Be === "loading";
    fr.current = Z.useMemo(() => Ho * j + qa, [Ho, qa]), Z.useEffect(() => {
        Ln.current = Ga
    }, [Ga]), Z.useEffect(() => {
        Y(!0)
    }, []), Z.useEffect(() => {
        let oe = Ct.current;
        if (oe) {
            let ye = oe.getBoundingClientRect().height;
            return cr(ye), b(be => [{
                toastId: h.id,
                height: ye,
                position: h.position
            }, ...be]), () => b(be => be.filter(St => St.toastId !== h.id))
        }
    }, [b, h.id]), Z.useLayoutEffect(() => {
        if (!F) return;
        let oe = Ct.current, ye = oe.style.height;
        oe.style.height = "auto";
        let be = oe.getBoundingClientRect().height;
        oe.style.height = ye, cr(be), b(St => St.find(Nt => Nt.toastId === h.id) ? St.map(Nt => Nt.toastId === h.id ? {
            ...Nt,
            height: be
        } : Nt) : [{toastId: h.id, height: be, position: h.position}, ...St])
    }, [F, h.title, h.description, b, h.id]);
    let xn = Z.useCallback(() => {
        te(!0), $n(fr.current), b(oe => oe.filter(ye => ye.toastId !== h.id)), setTimeout(() => {
            N(h)
        }, uT)
    }, [h, N, b, fr]);
    Z.useEffect(() => {
        if (h.promise && Be === "loading" || h.duration === 1 / 0 || h.type === "loading") return;
        let oe;
        return S || m || I && Qa ? (() => {
            if (Xa.current < si.current) {
                let ye = new Date().getTime() - si.current;
                Ln.current = Ln.current - ye
            }
            Xa.current = new Date().getTime()
        })() : Ln.current !== 1 / 0 && (si.current = new Date().getTime(), oe = setTimeout(() => {
            var ye;
            (ye = h.onAutoClose) == null || ye.call(h, h), xn()
        }, Ln.current)), () => clearTimeout(oe)
    }, [S, m, h, Be, I, Qa, xn]), Z.useEffect(() => {
        h.delete && xn()
    }, [xn, h.delete]);

    function Za() {
        var oe, ye, be;
        return R != null && R.loading ? Z.createElement("div", {
            className: qt(_ == null ? void 0 : _.loader, (oe = h == null ? void 0 : h.classNames) == null ? void 0 : oe.loader, "sonner-loader"),
            "data-visible": Be === "loading"
        }, R.loading) : A ? Z.createElement("div", {
            className: qt(_ == null ? void 0 : _.loader, (ye = h == null ? void 0 : h.classNames) == null ? void 0 : ye.loader, "sonner-loader"),
            "data-visible": Be === "loading"
        }, A) : Z.createElement(UP, {
            className: qt(_ == null ? void 0 : _.loader, (be = h == null ? void 0 : h.classNames) == null ? void 0 : be.loader),
            visible: Be === "loading"
        })
    }

    return Z.createElement("li", {
        tabIndex: 0,
        ref: Ct,
        className: qt($, td, _ == null ? void 0 : _.toast, (t = h == null ? void 0 : h.classNames) == null ? void 0 : t.toast, _ == null ? void 0 : _.default, _ == null ? void 0 : _[Be], (n = h == null ? void 0 : h.classNames) == null ? void 0 : n[Be]),
        "data-sonner-toast": "",
        "data-rich-colors": (r = h.richColors) != null ? r : E,
        "data-styled": !(h.jsx || h.unstyled || x),
        "data-mounted": F,
        "data-promise": !!h.promise,
        "data-swiped": we,
        "data-removed": X,
        "data-visible": dr,
        "data-y-position": rd,
        "data-x-position": od,
        "data-index": v,
        "data-front": ri,
        "data-swiping": ee,
        "data-dismissible": Gt,
        "data-type": Be,
        "data-invert": sd,
        "data-swipe-out": ue,
        "data-swipe-direction": U,
        "data-expanded": !!(S || P && F),
        style: {
            "--index": v,
            "--toasts-before": v,
            "--z-index": C.length - v,
            "--offset": `${X ? pt : fr.current}px`,
            "--initial-height": P ? "auto" : `${yn}px`, ...O, ...h.style
        },
        onDragEnd: () => {
            Q(!1), H(null), pr.current = null
        },
        onPointerDown: oe => {
            ii || !Gt || (ur.current = new Date, $n(fr.current), oe.target.setPointerCapture(oe.pointerId), oe.target.tagName !== "BUTTON" && (Q(!0), pr.current = {
                x: oe.clientX,
                y: oe.clientY
            }))
        },
        onPointerUp: () => {
            var oe, ye, be, St;
            if (ue || !Gt) return;
            pr.current = null;
            let Nt = Number(((oe = Ct.current) == null ? void 0 : oe.style.getPropertyValue("--swipe-amount-x").replace("px", "")) || 0),
                wn = Number(((ye = Ct.current) == null ? void 0 : ye.style.getPropertyValue("--swipe-amount-y").replace("px", "")) || 0),
                Fn = new Date().getTime() - ((be = ur.current) == null ? void 0 : be.getTime()),
                Et = V === "x" ? Nt : wn, bn = Math.abs(Et) / Fn;
            if (Math.abs(Et) >= cT || bn > .11) {
                $n(fr.current), (St = h.onDismiss) == null || St.call(h, h), K(V === "x" ? Nt > 0 ? "right" : "left" : wn > 0 ? "down" : "up"), xn(), ie(!0), Ne(!1);
                return
            }
            Q(!1), H(null)
        },
        onPointerMove: oe => {
            var ye, be, St, Nt;
            if (!pr.current || !Gt || ((ye = window.getSelection()) == null ? void 0 : ye.toString().length) > 0) return;
            let wn = oe.clientY - pr.current.y, Fn = oe.clientX - pr.current.x,
                Et = (be = e.swipeDirections) != null ? be : dT(M);
            !V && (Math.abs(Fn) > 1 || Math.abs(wn) > 1) && H(Math.abs(Fn) > Math.abs(wn) ? "x" : "y");
            let bn = {x: 0, y: 0};
            V === "y" ? (Et.includes("top") || Et.includes("bottom")) && (Et.includes("top") && wn < 0 || Et.includes("bottom") && wn > 0) && (bn.y = wn) : V === "x" && (Et.includes("left") || Et.includes("right")) && (Et.includes("left") && Fn < 0 || Et.includes("right") && Fn > 0) && (bn.x = Fn), (Math.abs(bn.x) > 0 || Math.abs(bn.y) > 0) && Ne(!0), (St = Ct.current) == null || St.style.setProperty("--swipe-amount-x", `${bn.x}px`), (Nt = Ct.current) == null || Nt.style.setProperty("--swipe-amount-y", `${bn.y}px`)
        }
    }, nd && !h.jsx ? Z.createElement("button", {
        "aria-label": z,
        "data-disabled": ii,
        "data-close-button": !0,
        onClick: ii || !Gt ? () => {
        } : () => {
            var oe;
            xn(), (oe = h.onDismiss) == null || oe.call(h, h)
        },
        className: qt(_ == null ? void 0 : _.closeButton, (o = h == null ? void 0 : h.classNames) == null ? void 0 : o.closeButton)
    }, (s = R == null ? void 0 : R.close) != null ? s : XP) : null, h.jsx || g.isValidElement(h.title) ? h.jsx ? h.jsx : typeof h.title == "function" ? h.title() : h.title : Z.createElement(Z.Fragment, null, Be || h.icon || h.promise ? Z.createElement("div", {
        "data-icon": "",
        className: qt(_ == null ? void 0 : _.icon, (i = h == null ? void 0 : h.classNames) == null ? void 0 : i.icon)
    }, h.promise || h.type === "loading" && !h.icon ? h.icon || Za() : null, h.type !== "loading" ? h.icon || (R == null ? void 0 : R[Be]) || VP(Be) : null) : null, Z.createElement("div", {
        "data-content": "",
        className: qt(_ == null ? void 0 : _.content, (a = h == null ? void 0 : h.classNames) == null ? void 0 : a.content)
    }, Z.createElement("div", {
        "data-title": "",
        className: qt(_ == null ? void 0 : _.title, (l = h == null ? void 0 : h.classNames) == null ? void 0 : l.title)
    }, typeof h.title == "function" ? h.title() : h.title), h.description ? Z.createElement("div", {
        "data-description": "",
        className: qt(B, oi, _ == null ? void 0 : _.description, (u = h == null ? void 0 : h.classNames) == null ? void 0 : u.description)
    }, typeof h.description == "function" ? h.description() : h.description) : null), g.isValidElement(h.cancel) ? h.cancel : h.cancel && wl(h.cancel) ? Z.createElement("button", {
        "data-button": !0,
        "data-cancel": !0,
        style: h.cancelButtonStyle || D,
        onClick: oe => {
            var ye, be;
            wl(h.cancel) && Gt && ((be = (ye = h.cancel).onClick) == null || be.call(ye, oe), xn())
        },
        className: qt(_ == null ? void 0 : _.cancelButton, (d = h == null ? void 0 : h.classNames) == null ? void 0 : d.cancelButton)
    }, h.cancel.label) : null, g.isValidElement(h.action) ? h.action : h.action && wl(h.action) ? Z.createElement("button", {
        "data-button": !0,
        "data-action": !0,
        style: h.actionButtonStyle || L,
        onClick: oe => {
            var ye, be;
            wl(h.action) && ((be = (ye = h.action).onClick) == null || be.call(ye, oe), !oe.defaultPrevented && xn())
        },
        className: qt(_ == null ? void 0 : _.actionButton, (f = h == null ? void 0 : h.classNames) == null ? void 0 : f.actionButton)
    }, h.action.label) : null))
};

function gv() {
    if (typeof window > "u" || typeof document > "u") return "ltr";
    let e = document.documentElement.getAttribute("dir");
    return e === "auto" || !e ? window.getComputedStyle(document.documentElement).direction : e
}

function pT(e, t) {
    let n = {};
    return [e, t].forEach((r, o) => {
        let s = o === 1, i = s ? "--mobile-offset" : "--offset", a = s ? iT : sT;

        function l(u) {
            ["top", "right", "bottom", "left"].forEach(d => {
                n[`${i}-${d}`] = typeof u == "number" ? `${u}px` : u
            })
        }

        typeof r == "number" || typeof r == "string" ? l(r) : typeof r == "object" ? ["top", "right", "bottom", "left"].forEach(u => {
            r[u] === void 0 ? n[`${i}-${u}`] = a : n[`${i}-${u}`] = typeof r[u] == "number" ? `${r[u]}px` : r[u]
        }) : l(a)
    }), n
}

var hT = g.forwardRef(function (e, t) {
    let {
            invert: n,
            position: r = "bottom-right",
            hotkey: o = ["altKey", "KeyT"],
            expand: s,
            closeButton: i,
            className: a,
            offset: l,
            mobileOffset: u,
            theme: d = "light",
            richColors: f,
            duration: p,
            style: h,
            visibleToasts: x = oT,
            toastOptions: m,
            dir: b = gv(),
            gap: y = lT,
            loadingIcon: w,
            icons: v,
            containerAriaLabel: C = "Notifications",
            pauseWhenPageIsHidden: S
        } = e, [N, E] = Z.useState([]),
        T = Z.useMemo(() => Array.from(new Set([r].concat(N.filter(I => I.position).map(I => I.position)))), [N, r]), [O, D] = Z.useState([]), [L, $] = Z.useState(!1), [B, k] = Z.useState(!1), [M, j] = Z.useState(d !== "system" ? d : typeof window < "u" && window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"),
        A = Z.useRef(null), P = o.join("+").replace(/Key/g, "").replace(/Digit/g, ""), _ = Z.useRef(null),
        R = Z.useRef(!1), z = Z.useCallback(I => {
            E(V => {
                var H;
                return (H = V.find(U => U.id === I.id)) != null && H.delete || ht.dismiss(I.id), V.filter(({id: U}) => U !== I.id)
            })
        }, []);
    return Z.useEffect(() => ht.subscribe(I => {
        if (I.dismiss) {
            E(V => V.map(H => H.id === I.id ? {...H, delete: !0} : H));
            return
        }
        setTimeout(() => {
            Sw.flushSync(() => {
                E(V => {
                    let H = V.findIndex(U => U.id === I.id);
                    return H !== -1 ? [...V.slice(0, H), {...V[H], ...I}, ...V.slice(H + 1)] : [I, ...V]
                })
            })
        })
    }), []), Z.useEffect(() => {
        if (d !== "system") {
            j(d);
            return
        }
        if (d === "system" && (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? j("dark") : j("light")), typeof window > "u") return;
        let I = window.matchMedia("(prefers-color-scheme: dark)");
        try {
            I.addEventListener("change", ({matches: V}) => {
                j(V ? "dark" : "light")
            })
        } catch {
            I.addListener(({matches: H}) => {
                try {
                    j(H ? "dark" : "light")
                } catch (U) {
                    console.error(U)
                }
            })
        }
    }, [d]), Z.useEffect(() => {
        N.length <= 1 && $(!1)
    }, [N]), Z.useEffect(() => {
        let I = V => {
            var H, U;
            o.every(K => V[K] || V.code === K) && ($(!0), (H = A.current) == null || H.focus()), V.code === "Escape" && (document.activeElement === A.current || (U = A.current) != null && U.contains(document.activeElement)) && $(!1)
        };
        return document.addEventListener("keydown", I), () => document.removeEventListener("keydown", I)
    }, [o]), Z.useEffect(() => {
        if (A.current) return () => {
            _.current && (_.current.focus({preventScroll: !0}), _.current = null, R.current = !1)
        }
    }, [A.current]), Z.createElement("section", {
        ref: t,
        "aria-label": `${C} ${P}`,
        tabIndex: -1,
        "aria-live": "polite",
        "aria-relevant": "additions text",
        "aria-atomic": "false",
        suppressHydrationWarning: !0
    }, T.map((I, V) => {
        var H;
        let [U, K] = I.split("-");
        return N.length ? Z.createElement("ol", {
            key: I,
            dir: b === "auto" ? gv() : b,
            tabIndex: -1,
            ref: A,
            className: a,
            "data-sonner-toaster": !0,
            "data-theme": M,
            "data-y-position": U,
            "data-lifted": L && N.length > 1 && !s,
            "data-x-position": K,
            style: {
                "--front-toast-height": `${((H = O[0]) == null ? void 0 : H.height) || 0}px`,
                "--width": `${aT}px`,
                "--gap": `${y}px`, ...h, ...pT(l, u)
            },
            onBlur: F => {
                R.current && !F.currentTarget.contains(F.relatedTarget) && (R.current = !1, _.current && (_.current.focus({preventScroll: !0}), _.current = null))
            },
            onFocus: F => {
                F.target instanceof HTMLElement && F.target.dataset.dismissible === "false" || R.current || (R.current = !0, _.current = F.relatedTarget)
            },
            onMouseEnter: () => $(!0),
            onMouseMove: () => $(!0),
            onMouseLeave: () => {
                B || $(!1)
            },
            onDragEnd: () => $(!1),
            onPointerDown: F => {
                F.target instanceof HTMLElement && F.target.dataset.dismissible === "false" || k(!0)
            },
            onPointerUp: () => k(!1)
        }, N.filter(F => !F.position && V === 0 || F.position === I).map((F, Y) => {
            var X, te;
            return Z.createElement(fT, {
                key: F.id,
                icons: v,
                index: Y,
                toast: F,
                defaultRichColors: f,
                duration: (X = m == null ? void 0 : m.duration) != null ? X : p,
                className: m == null ? void 0 : m.className,
                descriptionClassName: m == null ? void 0 : m.descriptionClassName,
                invert: n,
                visibleToasts: x,
                closeButton: (te = m == null ? void 0 : m.closeButton) != null ? te : i,
                interacting: B,
                position: I,
                style: m == null ? void 0 : m.style,
                unstyled: m == null ? void 0 : m.unstyled,
                classNames: m == null ? void 0 : m.classNames,
                cancelButtonStyle: m == null ? void 0 : m.cancelButtonStyle,
                actionButtonStyle: m == null ? void 0 : m.actionButtonStyle,
                removeToast: z,
                toasts: N.filter(ee => ee.position == F.position),
                heights: O.filter(ee => ee.position == F.position),
                setHeights: D,
                expandByDefault: s,
                gap: y,
                loadingIcon: w,
                expanded: L,
                pauseWhenPageIsHidden: S,
                swipeDirections: e.swipeDirections
            })
        })) : null
    }))
});
const mT = ({...e}) => {
    const {theme: t = "system"} = BP();
    return c.jsx(hT, {
        theme: t,
        className: "toaster group",
        toastOptions: {
            classNames: {
                toast: "group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg",
                description: "group-[.toast]:text-muted-foreground",
                actionButton: "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground",
                cancelButton: "group-[.toast]:bg-muted group-[.toast]:text-muted-foreground"
            }
        }, ...e
    })
};
var gT = A2[" useId ".trim().toString()] || (() => {
}), vT = 0;

function ct(e) {
    const [t, n] = g.useState(gT());
    return nt(() => {
        n(r => r ?? String(vT++))
    }, [e]), t ? `radix-${t}` : ""
}

const yT = ["top", "right", "bottom", "left"], Yr = Math.min, _t = Math.max, Pc = Math.round, bl = Math.floor,
    Mn = e => ({x: e, y: e}), xT = {left: "right", right: "left", bottom: "top", top: "bottom"},
    wT = {start: "end", end: "start"};

function lp(e, t, n) {
    return _t(e, Yr(t, n))
}

function tr(e, t) {
    return typeof e == "function" ? e(t) : e
}

function nr(e) {
    return e.split("-")[0]
}

function ei(e) {
    return e.split("-")[1]
}

function Wh(e) {
    return e === "x" ? "y" : "x"
}

function Kh(e) {
    return e === "y" ? "height" : "width"
}

function Gr(e) {
    return ["top", "bottom"].includes(nr(e)) ? "y" : "x"
}

function Yh(e) {
    return Wh(Gr(e))
}

function bT(e, t, n) {
    n === void 0 && (n = !1);
    const r = ei(e), o = Yh(e), s = Kh(o);
    let i = o === "x" ? r === (n ? "end" : "start") ? "right" : "left" : r === "start" ? "bottom" : "top";
    return t.reference[s] > t.floating[s] && (i = Tc(i)), [i, Tc(i)]
}

function CT(e) {
    const t = Tc(e);
    return [cp(e), t, cp(t)]
}

function cp(e) {
    return e.replace(/start|end/g, t => wT[t])
}

function ST(e, t, n) {
    const r = ["left", "right"], o = ["right", "left"], s = ["top", "bottom"], i = ["bottom", "top"];
    switch (e) {
        case"top":
        case"bottom":
            return n ? t ? o : r : t ? r : o;
        case"left":
        case"right":
            return t ? s : i;
        default:
            return []
    }
}

function NT(e, t, n, r) {
    const o = ei(e);
    let s = ST(nr(e), n === "start", r);
    return o && (s = s.map(i => i + "-" + o), t && (s = s.concat(s.map(cp)))), s
}

function Tc(e) {
    return e.replace(/left|right|bottom|top/g, t => xT[t])
}

function ET(e) {
    return {top: 0, right: 0, bottom: 0, left: 0, ...e}
}

function v1(e) {
    return typeof e != "number" ? ET(e) : {top: e, right: e, bottom: e, left: e}
}

function Mc(e) {
    const {x: t, y: n, width: r, height: o} = e;
    return {width: r, height: o, top: n, left: t, right: t + r, bottom: n + o, x: t, y: n}
}

function vv(e, t, n) {
    let {reference: r, floating: o} = e;
    const s = Gr(t), i = Yh(t), a = Kh(i), l = nr(t), u = s === "y", d = r.x + r.width / 2 - o.width / 2,
        f = r.y + r.height / 2 - o.height / 2, p = r[a] / 2 - o[a] / 2;
    let h;
    switch (l) {
        case"top":
            h = {x: d, y: r.y - o.height};
            break;
        case"bottom":
            h = {x: d, y: r.y + r.height};
            break;
        case"right":
            h = {x: r.x + r.width, y: f};
            break;
        case"left":
            h = {x: r.x - o.width, y: f};
            break;
        default:
            h = {x: r.x, y: r.y}
    }
    switch (ei(t)) {
        case"start":
            h[i] -= p * (n && u ? -1 : 1);
            break;
        case"end":
            h[i] += p * (n && u ? -1 : 1);
            break
    }
    return h
}

const kT = async (e, t, n) => {
    const {placement: r = "bottom", strategy: o = "absolute", middleware: s = [], platform: i} = n,
        a = s.filter(Boolean), l = await (i.isRTL == null ? void 0 : i.isRTL(t));
    let u = await i.getElementRects({reference: e, floating: t, strategy: o}), {x: d, y: f} = vv(u, r, l), p = r,
        h = {}, x = 0;
    for (let m = 0; m < a.length; m++) {
        const {name: b, fn: y} = a[m], {x: w, y: v, data: C, reset: S} = await y({
            x: d,
            y: f,
            initialPlacement: r,
            placement: p,
            strategy: o,
            middlewareData: h,
            rects: u,
            platform: i,
            elements: {reference: e, floating: t}
        });
        d = w ?? d, f = v ?? f, h = {
            ...h,
            [b]: {...h[b], ...C}
        }, S && x <= 50 && (x++, typeof S == "object" && (S.placement && (p = S.placement), S.rects && (u = S.rects === !0 ? await i.getElementRects({
            reference: e,
            floating: t,
            strategy: o
        }) : S.rects), {x: d, y: f} = vv(u, p, l)), m = -1)
    }
    return {x: d, y: f, placement: p, strategy: o, middlewareData: h}
};

async function oa(e, t) {
    var n;
    t === void 0 && (t = {});
    const {x: r, y: o, platform: s, rects: i, elements: a, strategy: l} = e, {
            boundary: u = "clippingAncestors",
            rootBoundary: d = "viewport",
            elementContext: f = "floating",
            altBoundary: p = !1,
            padding: h = 0
        } = tr(t, e), x = v1(h), b = a[p ? f === "floating" ? "reference" : "floating" : f],
        y = Mc(await s.getClippingRect({
            element: (n = await (s.isElement == null ? void 0 : s.isElement(b))) == null || n ? b : b.contextElement || await (s.getDocumentElement == null ? void 0 : s.getDocumentElement(a.floating)),
            boundary: u,
            rootBoundary: d,
            strategy: l
        })), w = f === "floating" ? {x: r, y: o, width: i.floating.width, height: i.floating.height} : i.reference,
        v = await (s.getOffsetParent == null ? void 0 : s.getOffsetParent(a.floating)),
        C = await (s.isElement == null ? void 0 : s.isElement(v)) ? await (s.getScale == null ? void 0 : s.getScale(v)) || {
            x: 1,
            y: 1
        } : {x: 1, y: 1},
        S = Mc(s.convertOffsetParentRelativeRectToViewportRelativeRect ? await s.convertOffsetParentRelativeRectToViewportRelativeRect({
            elements: a,
            rect: w,
            offsetParent: v,
            strategy: l
        }) : w);
    return {
        top: (y.top - S.top + x.top) / C.y,
        bottom: (S.bottom - y.bottom + x.bottom) / C.y,
        left: (y.left - S.left + x.left) / C.x,
        right: (S.right - y.right + x.right) / C.x
    }
}

const _T = e => ({
    name: "arrow", options: e, async fn(t) {
        const {x: n, y: r, placement: o, rects: s, platform: i, elements: a, middlewareData: l} = t, {
            element: u,
            padding: d = 0
        } = tr(e, t) || {};
        if (u == null) return {};
        const f = v1(d), p = {x: n, y: r}, h = Yh(o), x = Kh(h), m = await i.getDimensions(u), b = h === "y",
            y = b ? "top" : "left", w = b ? "bottom" : "right", v = b ? "clientHeight" : "clientWidth",
            C = s.reference[x] + s.reference[h] - p[h] - s.floating[x], S = p[h] - s.reference[h],
            N = await (i.getOffsetParent == null ? void 0 : i.getOffsetParent(u));
        let E = N ? N[v] : 0;
        (!E || !await (i.isElement == null ? void 0 : i.isElement(N))) && (E = a.floating[v] || s.floating[x]);
        const T = C / 2 - S / 2, O = E / 2 - m[x] / 2 - 1, D = Yr(f[y], O), L = Yr(f[w], O), $ = D, B = E - m[x] - L,
            k = E / 2 - m[x] / 2 + T, M = lp($, k, B),
            j = !l.arrow && ei(o) != null && k !== M && s.reference[x] / 2 - (k < $ ? D : L) - m[x] / 2 < 0,
            A = j ? k < $ ? k - $ : k - B : 0;
        return {[h]: p[h] + A, data: {[h]: M, centerOffset: k - M - A, ...j && {alignmentOffset: A}}, reset: j}
    }
}), jT = function (e) {
    return e === void 0 && (e = {}), {
        name: "flip", options: e, async fn(t) {
            var n, r;
            const {
                placement: o,
                middlewareData: s,
                rects: i,
                initialPlacement: a,
                platform: l,
                elements: u
            } = t, {
                mainAxis: d = !0,
                crossAxis: f = !0,
                fallbackPlacements: p,
                fallbackStrategy: h = "bestFit",
                fallbackAxisSideDirection: x = "none",
                flipAlignment: m = !0,
                ...b
            } = tr(e, t);
            if ((n = s.arrow) != null && n.alignmentOffset) return {};
            const y = nr(o), w = Gr(a), v = nr(a) === a, C = await (l.isRTL == null ? void 0 : l.isRTL(u.floating)),
                S = p || (v || !m ? [Tc(a)] : CT(a)), N = x !== "none";
            !p && N && S.push(...NT(a, m, x, C));
            const E = [a, ...S], T = await oa(t, b), O = [];
            let D = ((r = s.flip) == null ? void 0 : r.overflows) || [];
            if (d && O.push(T[y]), f) {
                const k = bT(o, i, C);
                O.push(T[k[0]], T[k[1]])
            }
            if (D = [...D, {placement: o, overflows: O}], !O.every(k => k <= 0)) {
                var L, $;
                const k = (((L = s.flip) == null ? void 0 : L.index) || 0) + 1, M = E[k];
                if (M) return {data: {index: k, overflows: D}, reset: {placement: M}};
                let j = ($ = D.filter(A => A.overflows[0] <= 0).sort((A, P) => A.overflows[1] - P.overflows[1])[0]) == null ? void 0 : $.placement;
                if (!j) switch (h) {
                    case"bestFit": {
                        var B;
                        const A = (B = D.filter(P => {
                            if (N) {
                                const _ = Gr(P.placement);
                                return _ === w || _ === "y"
                            }
                            return !0
                        }).map(P => [P.placement, P.overflows.filter(_ => _ > 0).reduce((_, R) => _ + R, 0)]).sort((P, _) => P[1] - _[1])[0]) == null ? void 0 : B[0];
                        A && (j = A);
                        break
                    }
                    case"initialPlacement":
                        j = a;
                        break
                }
                if (o !== j) return {reset: {placement: j}}
            }
            return {}
        }
    }
};

function yv(e, t) {
    return {top: e.top - t.height, right: e.right - t.width, bottom: e.bottom - t.height, left: e.left - t.width}
}

function xv(e) {
    return yT.some(t => e[t] >= 0)
}

const PT = function (e) {
    return e === void 0 && (e = {}), {
        name: "hide", options: e, async fn(t) {
            const {rects: n} = t, {strategy: r = "referenceHidden", ...o} = tr(e, t);
            switch (r) {
                case"referenceHidden": {
                    const s = await oa(t, {...o, elementContext: "reference"}), i = yv(s, n.reference);
                    return {data: {referenceHiddenOffsets: i, referenceHidden: xv(i)}}
                }
                case"escaped": {
                    const s = await oa(t, {...o, altBoundary: !0}), i = yv(s, n.floating);
                    return {data: {escapedOffsets: i, escaped: xv(i)}}
                }
                default:
                    return {}
            }
        }
    }
};

async function TT(e, t) {
    const {placement: n, platform: r, elements: o} = e, s = await (r.isRTL == null ? void 0 : r.isRTL(o.floating)),
        i = nr(n), a = ei(n), l = Gr(n) === "y", u = ["left", "top"].includes(i) ? -1 : 1, d = s && l ? -1 : 1,
        f = tr(t, e);
    let {mainAxis: p, crossAxis: h, alignmentAxis: x} = typeof f == "number" ? {
        mainAxis: f,
        crossAxis: 0,
        alignmentAxis: null
    } : {mainAxis: f.mainAxis || 0, crossAxis: f.crossAxis || 0, alignmentAxis: f.alignmentAxis};
    return a && typeof x == "number" && (h = a === "end" ? x * -1 : x), l ? {x: h * d, y: p * u} : {x: p * u, y: h * d}
}

const MT = function (e) {
    return e === void 0 && (e = 0), {
        name: "offset", options: e, async fn(t) {
            var n, r;
            const {x: o, y: s, placement: i, middlewareData: a} = t, l = await TT(t, e);
            return i === ((n = a.offset) == null ? void 0 : n.placement) && (r = a.arrow) != null && r.alignmentOffset ? {} : {
                x: o + l.x,
                y: s + l.y,
                data: {...l, placement: i}
            }
        }
    }
}, RT = function (e) {
    return e === void 0 && (e = {}), {
        name: "shift", options: e, async fn(t) {
            const {x: n, y: r, placement: o} = t, {
                mainAxis: s = !0, crossAxis: i = !1, limiter: a = {
                    fn: b => {
                        let {x: y, y: w} = b;
                        return {x: y, y: w}
                    }
                }, ...l
            } = tr(e, t), u = {x: n, y: r}, d = await oa(t, l), f = Gr(nr(o)), p = Wh(f);
            let h = u[p], x = u[f];
            if (s) {
                const b = p === "y" ? "top" : "left", y = p === "y" ? "bottom" : "right", w = h + d[b], v = h - d[y];
                h = lp(w, h, v)
            }
            if (i) {
                const b = f === "y" ? "top" : "left", y = f === "y" ? "bottom" : "right", w = x + d[b], v = x - d[y];
                x = lp(w, x, v)
            }
            const m = a.fn({...t, [p]: h, [f]: x});
            return {...m, data: {x: m.x - n, y: m.y - r, enabled: {[p]: s, [f]: i}}}
        }
    }
}, AT = function (e) {
    return e === void 0 && (e = {}), {
        options: e, fn(t) {
            const {x: n, y: r, placement: o, rects: s, middlewareData: i} = t, {
                offset: a = 0,
                mainAxis: l = !0,
                crossAxis: u = !0
            } = tr(e, t), d = {x: n, y: r}, f = Gr(o), p = Wh(f);
            let h = d[p], x = d[f];
            const m = tr(a, t),
                b = typeof m == "number" ? {mainAxis: m, crossAxis: 0} : {mainAxis: 0, crossAxis: 0, ...m};
            if (l) {
                const v = p === "y" ? "height" : "width", C = s.reference[p] - s.floating[v] + b.mainAxis,
                    S = s.reference[p] + s.reference[v] - b.mainAxis;
                h < C ? h = C : h > S && (h = S)
            }
            if (u) {
                var y, w;
                const v = p === "y" ? "width" : "height", C = ["top", "left"].includes(nr(o)),
                    S = s.reference[f] - s.floating[v] + (C && ((y = i.offset) == null ? void 0 : y[f]) || 0) + (C ? 0 : b.crossAxis),
                    N = s.reference[f] + s.reference[v] + (C ? 0 : ((w = i.offset) == null ? void 0 : w[f]) || 0) - (C ? b.crossAxis : 0);
                x < S ? x = S : x > N && (x = N)
            }
            return {[p]: h, [f]: x}
        }
    }
}, IT = function (e) {
    return e === void 0 && (e = {}), {
        name: "size", options: e, async fn(t) {
            var n, r;
            const {placement: o, rects: s, platform: i, elements: a} = t, {
                apply: l = () => {
                }, ...u
            } = tr(e, t), d = await oa(t, u), f = nr(o), p = ei(o), h = Gr(o) === "y", {
                width: x,
                height: m
            } = s.floating;
            let b, y;
            f === "top" || f === "bottom" ? (b = f, y = p === (await (i.isRTL == null ? void 0 : i.isRTL(a.floating)) ? "start" : "end") ? "left" : "right") : (y = f, b = p === "end" ? "top" : "bottom");
            const w = m - d.top - d.bottom, v = x - d.left - d.right, C = Yr(m - d[b], w), S = Yr(x - d[y], v),
                N = !t.middlewareData.shift;
            let E = C, T = S;
            if ((n = t.middlewareData.shift) != null && n.enabled.x && (T = v), (r = t.middlewareData.shift) != null && r.enabled.y && (E = w), N && !p) {
                const D = _t(d.left, 0), L = _t(d.right, 0), $ = _t(d.top, 0), B = _t(d.bottom, 0);
                h ? T = x - 2 * (D !== 0 || L !== 0 ? D + L : _t(d.left, d.right)) : E = m - 2 * ($ !== 0 || B !== 0 ? $ + B : _t(d.top, d.bottom))
            }
            await l({...t, availableWidth: T, availableHeight: E});
            const O = await i.getDimensions(a.floating);
            return x !== O.width || m !== O.height ? {reset: {rects: !0}} : {}
        }
    }
};

function vu() {
    return typeof window < "u"
}

function ti(e) {
    return y1(e) ? (e.nodeName || "").toLowerCase() : "#document"
}

function Mt(e) {
    var t;
    return (e == null || (t = e.ownerDocument) == null ? void 0 : t.defaultView) || window
}

function On(e) {
    var t;
    return (t = (y1(e) ? e.ownerDocument : e.document) || window.document) == null ? void 0 : t.documentElement
}

function y1(e) {
    return vu() ? e instanceof Node || e instanceof Mt(e).Node : !1
}

function pn(e) {
    return vu() ? e instanceof Element || e instanceof Mt(e).Element : !1
}

function An(e) {
    return vu() ? e instanceof HTMLElement || e instanceof Mt(e).HTMLElement : !1
}

function wv(e) {
    return !vu() || typeof ShadowRoot > "u" ? !1 : e instanceof ShadowRoot || e instanceof Mt(e).ShadowRoot
}

function Ta(e) {
    const {overflow: t, overflowX: n, overflowY: r, display: o} = hn(e);
    return /auto|scroll|overlay|hidden|clip/.test(t + r + n) && !["inline", "contents"].includes(o)
}

function OT(e) {
    return ["table", "td", "th"].includes(ti(e))
}

function yu(e) {
    return [":popover-open", ":modal"].some(t => {
        try {
            return e.matches(t)
        } catch {
            return !1
        }
    })
}

function Gh(e) {
    const t = Xh(), n = pn(e) ? hn(e) : e;
    return ["transform", "translate", "scale", "rotate", "perspective"].some(r => n[r] ? n[r] !== "none" : !1) || (n.containerType ? n.containerType !== "normal" : !1) || !t && (n.backdropFilter ? n.backdropFilter !== "none" : !1) || !t && (n.filter ? n.filter !== "none" : !1) || ["transform", "translate", "scale", "rotate", "perspective", "filter"].some(r => (n.willChange || "").includes(r)) || ["paint", "layout", "strict", "content"].some(r => (n.contain || "").includes(r))
}

function DT(e) {
    let t = Xr(e);
    for (; An(t) && !Ls(t);) {
        if (Gh(t)) return t;
        if (yu(t)) return null;
        t = Xr(t)
    }
    return null
}

function Xh() {
    return typeof CSS > "u" || !CSS.supports ? !1 : CSS.supports("-webkit-backdrop-filter", "none")
}

function Ls(e) {
    return ["html", "body", "#document"].includes(ti(e))
}

function hn(e) {
    return Mt(e).getComputedStyle(e)
}

function xu(e) {
    return pn(e) ? {scrollLeft: e.scrollLeft, scrollTop: e.scrollTop} : {scrollLeft: e.scrollX, scrollTop: e.scrollY}
}

function Xr(e) {
    if (ti(e) === "html") return e;
    const t = e.assignedSlot || e.parentNode || wv(e) && e.host || On(e);
    return wv(t) ? t.host : t
}

function x1(e) {
    const t = Xr(e);
    return Ls(t) ? e.ownerDocument ? e.ownerDocument.body : e.body : An(t) && Ta(t) ? t : x1(t)
}

function sa(e, t, n) {
    var r;
    t === void 0 && (t = []), n === void 0 && (n = !0);
    const o = x1(e), s = o === ((r = e.ownerDocument) == null ? void 0 : r.body), i = Mt(o);
    if (s) {
        const a = up(i);
        return t.concat(i, i.visualViewport || [], Ta(o) ? o : [], a && n ? sa(a) : [])
    }
    return t.concat(o, sa(o, [], n))
}

function up(e) {
    return e.parent && Object.getPrototypeOf(e.parent) ? e.frameElement : null
}

function w1(e) {
    const t = hn(e);
    let n = parseFloat(t.width) || 0, r = parseFloat(t.height) || 0;
    const o = An(e), s = o ? e.offsetWidth : n, i = o ? e.offsetHeight : r, a = Pc(n) !== s || Pc(r) !== i;
    return a && (n = s, r = i), {width: n, height: r, $: a}
}

function qh(e) {
    return pn(e) ? e : e.contextElement
}

function ys(e) {
    const t = qh(e);
    if (!An(t)) return Mn(1);
    const n = t.getBoundingClientRect(), {width: r, height: o, $: s} = w1(t);
    let i = (s ? Pc(n.width) : n.width) / r, a = (s ? Pc(n.height) : n.height) / o;
    return (!i || !Number.isFinite(i)) && (i = 1), (!a || !Number.isFinite(a)) && (a = 1), {x: i, y: a}
}

const $T = Mn(0);

function b1(e) {
    const t = Mt(e);
    return !Xh() || !t.visualViewport ? $T : {x: t.visualViewport.offsetLeft, y: t.visualViewport.offsetTop}
}

function LT(e, t, n) {
    return t === void 0 && (t = !1), !n || t && n !== Mt(e) ? !1 : t
}

function Mo(e, t, n, r) {
    t === void 0 && (t = !1), n === void 0 && (n = !1);
    const o = e.getBoundingClientRect(), s = qh(e);
    let i = Mn(1);
    t && (r ? pn(r) && (i = ys(r)) : i = ys(e));
    const a = LT(s, n, r) ? b1(s) : Mn(0);
    let l = (o.left + a.x) / i.x, u = (o.top + a.y) / i.y, d = o.width / i.x, f = o.height / i.y;
    if (s) {
        const p = Mt(s), h = r && pn(r) ? Mt(r) : r;
        let x = p, m = up(x);
        for (; m && r && h !== x;) {
            const b = ys(m), y = m.getBoundingClientRect(), w = hn(m),
                v = y.left + (m.clientLeft + parseFloat(w.paddingLeft)) * b.x,
                C = y.top + (m.clientTop + parseFloat(w.paddingTop)) * b.y;
            l *= b.x, u *= b.y, d *= b.x, f *= b.y, l += v, u += C, x = Mt(m), m = up(x)
        }
    }
    return Mc({width: d, height: f, x: l, y: u})
}

function Qh(e, t) {
    const n = xu(e).scrollLeft;
    return t ? t.left + n : Mo(On(e)).left + n
}

function C1(e, t, n) {
    n === void 0 && (n = !1);
    const r = e.getBoundingClientRect(), o = r.left + t.scrollLeft - (n ? 0 : Qh(e, r)), s = r.top + t.scrollTop;
    return {x: o, y: s}
}

function FT(e) {
    let {elements: t, rect: n, offsetParent: r, strategy: o} = e;
    const s = o === "fixed", i = On(r), a = t ? yu(t.floating) : !1;
    if (r === i || a && s) return n;
    let l = {scrollLeft: 0, scrollTop: 0}, u = Mn(1);
    const d = Mn(0), f = An(r);
    if ((f || !f && !s) && ((ti(r) !== "body" || Ta(i)) && (l = xu(r)), An(r))) {
        const h = Mo(r);
        u = ys(r), d.x = h.x + r.clientLeft, d.y = h.y + r.clientTop
    }
    const p = i && !f && !s ? C1(i, l, !0) : Mn(0);
    return {
        width: n.width * u.x,
        height: n.height * u.y,
        x: n.x * u.x - l.scrollLeft * u.x + d.x + p.x,
        y: n.y * u.y - l.scrollTop * u.y + d.y + p.y
    }
}

function zT(e) {
    return Array.from(e.getClientRects())
}

function BT(e) {
    const t = On(e), n = xu(e), r = e.ownerDocument.body,
        o = _t(t.scrollWidth, t.clientWidth, r.scrollWidth, r.clientWidth),
        s = _t(t.scrollHeight, t.clientHeight, r.scrollHeight, r.clientHeight);
    let i = -n.scrollLeft + Qh(e);
    const a = -n.scrollTop;
    return hn(r).direction === "rtl" && (i += _t(t.clientWidth, r.clientWidth) - o), {width: o, height: s, x: i, y: a}
}

function VT(e, t) {
    const n = Mt(e), r = On(e), o = n.visualViewport;
    let s = r.clientWidth, i = r.clientHeight, a = 0, l = 0;
    if (o) {
        s = o.width, i = o.height;
        const u = Xh();
        (!u || u && t === "fixed") && (a = o.offsetLeft, l = o.offsetTop)
    }
    return {width: s, height: i, x: a, y: l}
}

function HT(e, t) {
    const n = Mo(e, !0, t === "fixed"), r = n.top + e.clientTop, o = n.left + e.clientLeft, s = An(e) ? ys(e) : Mn(1),
        i = e.clientWidth * s.x, a = e.clientHeight * s.y, l = o * s.x, u = r * s.y;
    return {width: i, height: a, x: l, y: u}
}

function bv(e, t, n) {
    let r;
    if (t === "viewport") r = VT(e, n); else if (t === "document") r = BT(On(e)); else if (pn(t)) r = HT(t, n); else {
        const o = b1(e);
        r = {x: t.x - o.x, y: t.y - o.y, width: t.width, height: t.height}
    }
    return Mc(r)
}

function S1(e, t) {
    const n = Xr(e);
    return n === t || !pn(n) || Ls(n) ? !1 : hn(n).position === "fixed" || S1(n, t)
}

function UT(e, t) {
    const n = t.get(e);
    if (n) return n;
    let r = sa(e, [], !1).filter(a => pn(a) && ti(a) !== "body"), o = null;
    const s = hn(e).position === "fixed";
    let i = s ? Xr(e) : e;
    for (; pn(i) && !Ls(i);) {
        const a = hn(i), l = Gh(i);
        !l && a.position === "fixed" && (o = null), (s ? !l && !o : !l && a.position === "static" && !!o && ["absolute", "fixed"].includes(o.position) || Ta(i) && !l && S1(e, i)) ? r = r.filter(d => d !== i) : o = a, i = Xr(i)
    }
    return t.set(e, r), r
}

function WT(e) {
    let {element: t, boundary: n, rootBoundary: r, strategy: o} = e;
    const i = [...n === "clippingAncestors" ? yu(t) ? [] : UT(t, this._c) : [].concat(n), r], a = i[0],
        l = i.reduce((u, d) => {
            const f = bv(t, d, o);
            return u.top = _t(f.top, u.top), u.right = Yr(f.right, u.right), u.bottom = Yr(f.bottom, u.bottom), u.left = _t(f.left, u.left), u
        }, bv(t, a, o));
    return {width: l.right - l.left, height: l.bottom - l.top, x: l.left, y: l.top}
}

function KT(e) {
    const {width: t, height: n} = w1(e);
    return {width: t, height: n}
}

function YT(e, t, n) {
    const r = An(t), o = On(t), s = n === "fixed", i = Mo(e, !0, s, t);
    let a = {scrollLeft: 0, scrollTop: 0};
    const l = Mn(0);
    if (r || !r && !s) if ((ti(t) !== "body" || Ta(o)) && (a = xu(t)), r) {
        const p = Mo(t, !0, s, t);
        l.x = p.x + t.clientLeft, l.y = p.y + t.clientTop
    } else o && (l.x = Qh(o));
    const u = o && !r && !s ? C1(o, a) : Mn(0), d = i.left + a.scrollLeft - l.x - u.x,
        f = i.top + a.scrollTop - l.y - u.y;
    return {x: d, y: f, width: i.width, height: i.height}
}

function Ld(e) {
    return hn(e).position === "static"
}

function Cv(e, t) {
    if (!An(e) || hn(e).position === "fixed") return null;
    if (t) return t(e);
    let n = e.offsetParent;
    return On(e) === n && (n = n.ownerDocument.body), n
}

function N1(e, t) {
    const n = Mt(e);
    if (yu(e)) return n;
    if (!An(e)) {
        let o = Xr(e);
        for (; o && !Ls(o);) {
            if (pn(o) && !Ld(o)) return o;
            o = Xr(o)
        }
        return n
    }
    let r = Cv(e, t);
    for (; r && OT(r) && Ld(r);) r = Cv(r, t);
    return r && Ls(r) && Ld(r) && !Gh(r) ? n : r || DT(e) || n
}

const GT = async function (e) {
    const t = this.getOffsetParent || N1, n = this.getDimensions, r = await n(e.floating);
    return {
        reference: YT(e.reference, await t(e.floating), e.strategy),
        floating: {x: 0, y: 0, width: r.width, height: r.height}
    }
};

function XT(e) {
    return hn(e).direction === "rtl"
}

const qT = {
    convertOffsetParentRelativeRectToViewportRelativeRect: FT,
    getDocumentElement: On,
    getClippingRect: WT,
    getOffsetParent: N1,
    getElementRects: GT,
    getClientRects: zT,
    getDimensions: KT,
    getScale: ys,
    isElement: pn,
    isRTL: XT
};

function E1(e, t) {
    return e.x === t.x && e.y === t.y && e.width === t.width && e.height === t.height
}

function QT(e, t) {
    let n = null, r;
    const o = On(e);

    function s() {
        var a;
        clearTimeout(r), (a = n) == null || a.disconnect(), n = null
    }

    function i(a, l) {
        a === void 0 && (a = !1), l === void 0 && (l = 1), s();
        const u = e.getBoundingClientRect(), {left: d, top: f, width: p, height: h} = u;
        if (a || t(), !p || !h) return;
        const x = bl(f), m = bl(o.clientWidth - (d + p)), b = bl(o.clientHeight - (f + h)), y = bl(d),
            v = {rootMargin: -x + "px " + -m + "px " + -b + "px " + -y + "px", threshold: _t(0, Yr(1, l)) || 1};
        let C = !0;

        function S(N) {
            const E = N[0].intersectionRatio;
            if (E !== l) {
                if (!C) return i();
                E ? i(!1, E) : r = setTimeout(() => {
                    i(!1, 1e-7)
                }, 1e3)
            }
            E === 1 && !E1(u, e.getBoundingClientRect()) && i(), C = !1
        }

        try {
            n = new IntersectionObserver(S, {...v, root: o.ownerDocument})
        } catch {
            n = new IntersectionObserver(S, v)
        }
        n.observe(e)
    }

    return i(!0), s
}

function ZT(e, t, n, r) {
    r === void 0 && (r = {});
    const {
        ancestorScroll: o = !0,
        ancestorResize: s = !0,
        elementResize: i = typeof ResizeObserver == "function",
        layoutShift: a = typeof IntersectionObserver == "function",
        animationFrame: l = !1
    } = r, u = qh(e), d = o || s ? [...u ? sa(u) : [], ...sa(t)] : [];
    d.forEach(y => {
        o && y.addEventListener("scroll", n, {passive: !0}), s && y.addEventListener("resize", n)
    });
    const f = u && a ? QT(u, n) : null;
    let p = -1, h = null;
    i && (h = new ResizeObserver(y => {
        let [w] = y;
        w && w.target === u && h && (h.unobserve(t), cancelAnimationFrame(p), p = requestAnimationFrame(() => {
            var v;
            (v = h) == null || v.observe(t)
        })), n()
    }), u && !l && h.observe(u), h.observe(t));
    let x, m = l ? Mo(e) : null;
    l && b();

    function b() {
        const y = Mo(e);
        m && !E1(m, y) && n(), m = y, x = requestAnimationFrame(b)
    }

    return n(), () => {
        var y;
        d.forEach(w => {
            o && w.removeEventListener("scroll", n), s && w.removeEventListener("resize", n)
        }), f == null || f(), (y = h) == null || y.disconnect(), h = null, l && cancelAnimationFrame(x)
    }
}

const JT = MT, eM = RT, tM = jT, nM = IT, rM = PT, Sv = _T, oM = AT, sM = (e, t, n) => {
    const r = new Map, o = {platform: qT, ...n}, s = {...o.platform, _c: r};
    return kT(e, t, {...o, platform: s})
};
var Ql = typeof document < "u" ? g.useLayoutEffect : g.useEffect;

function Rc(e, t) {
    if (e === t) return !0;
    if (typeof e != typeof t) return !1;
    if (typeof e == "function" && e.toString() === t.toString()) return !0;
    let n, r, o;
    if (e && t && typeof e == "object") {
        if (Array.isArray(e)) {
            if (n = e.length, n !== t.length) return !1;
            for (r = n; r-- !== 0;) if (!Rc(e[r], t[r])) return !1;
            return !0
        }
        if (o = Object.keys(e), n = o.length, n !== Object.keys(t).length) return !1;
        for (r = n; r-- !== 0;) if (!{}.hasOwnProperty.call(t, o[r])) return !1;
        for (r = n; r-- !== 0;) {
            const s = o[r];
            if (!(s === "_owner" && e.$$typeof) && !Rc(e[s], t[s])) return !1
        }
        return !0
    }
    return e !== e && t !== t
}

function k1(e) {
    return typeof window > "u" ? 1 : (e.ownerDocument.defaultView || window).devicePixelRatio || 1
}

function Nv(e, t) {
    const n = k1(e);
    return Math.round(t * n) / n
}

function Fd(e) {
    const t = g.useRef(e);
    return Ql(() => {
        t.current = e
    }), t
}

function iM(e) {
    e === void 0 && (e = {});
    const {
        placement: t = "bottom",
        strategy: n = "absolute",
        middleware: r = [],
        platform: o,
        elements: {reference: s, floating: i} = {},
        transform: a = !0,
        whileElementsMounted: l,
        open: u
    } = e, [d, f] = g.useState({
        x: 0,
        y: 0,
        strategy: n,
        placement: t,
        middlewareData: {},
        isPositioned: !1
    }), [p, h] = g.useState(r);
    Rc(p, r) || h(r);
    const [x, m] = g.useState(null), [b, y] = g.useState(null), w = g.useCallback(P => {
            P !== N.current && (N.current = P, m(P))
        }, []), v = g.useCallback(P => {
            P !== E.current && (E.current = P, y(P))
        }, []), C = s || x, S = i || b, N = g.useRef(null), E = g.useRef(null), T = g.useRef(d), O = l != null, D = Fd(l),
        L = Fd(o), $ = Fd(u), B = g.useCallback(() => {
            if (!N.current || !E.current) return;
            const P = {placement: t, strategy: n, middleware: p};
            L.current && (P.platform = L.current), sM(N.current, E.current, P).then(_ => {
                const R = {..._, isPositioned: $.current !== !1};
                k.current && !Rc(T.current, R) && (T.current = R, Fo.flushSync(() => {
                    f(R)
                }))
            })
        }, [p, t, n, L, $]);
    Ql(() => {
        u === !1 && T.current.isPositioned && (T.current.isPositioned = !1, f(P => ({...P, isPositioned: !1})))
    }, [u]);
    const k = g.useRef(!1);
    Ql(() => (k.current = !0, () => {
        k.current = !1
    }), []), Ql(() => {
        if (C && (N.current = C), S && (E.current = S), C && S) {
            if (D.current) return D.current(C, S, B);
            B()
        }
    }, [C, S, B, D, O]);
    const M = g.useMemo(() => ({reference: N, floating: E, setReference: w, setFloating: v}), [w, v]),
        j = g.useMemo(() => ({reference: C, floating: S}), [C, S]), A = g.useMemo(() => {
            const P = {position: n, left: 0, top: 0};
            if (!j.floating) return P;
            const _ = Nv(j.floating, d.x), R = Nv(j.floating, d.y);
            return a ? {
                ...P,
                transform: "translate(" + _ + "px, " + R + "px)", ...k1(j.floating) >= 1.5 && {willChange: "transform"}
            } : {position: n, left: _, top: R}
        }, [n, a, j.floating, d.x, d.y]);
    return g.useMemo(() => ({...d, update: B, refs: M, elements: j, floatingStyles: A}), [d, B, M, j, A])
}

const aM = e => {
        function t(n) {
            return {}.hasOwnProperty.call(n, "current")
        }

        return {
            name: "arrow", options: e, fn(n) {
                const {element: r, padding: o} = typeof e == "function" ? e(n) : e;
                return r && t(r) ? r.current != null ? Sv({element: r.current, padding: o}).fn(n) : {} : r ? Sv({
                    element: r,
                    padding: o
                }).fn(n) : {}
            }
        }
    }, lM = (e, t) => ({...JT(e), options: [e, t]}), cM = (e, t) => ({...eM(e), options: [e, t]}),
    uM = (e, t) => ({...oM(e), options: [e, t]}), dM = (e, t) => ({...tM(e), options: [e, t]}),
    fM = (e, t) => ({...nM(e), options: [e, t]}), pM = (e, t) => ({...rM(e), options: [e, t]}),
    hM = (e, t) => ({...aM(e), options: [e, t]});
var mM = "Arrow", _1 = g.forwardRef((e, t) => {
    const {children: n, width: r = 10, height: o = 5, ...s} = e;
    return c.jsx(re.svg, {
        ...s,
        ref: t,
        width: r,
        height: o,
        viewBox: "0 0 30 10",
        preserveAspectRatio: "none",
        children: e.asChild ? n : c.jsx("polygon", {points: "0,0 30,0 15,10"})
    })
});
_1.displayName = mM;
var gM = _1;

function vM(e) {
    const [t, n] = g.useState(void 0);
    return nt(() => {
        if (e) {
            n({width: e.offsetWidth, height: e.offsetHeight});
            const r = new ResizeObserver(o => {
                if (!Array.isArray(o) || !o.length) return;
                const s = o[0];
                let i, a;
                if ("borderBoxSize" in s) {
                    const l = s.borderBoxSize, u = Array.isArray(l) ? l[0] : l;
                    i = u.inlineSize, a = u.blockSize
                } else i = e.offsetWidth, a = e.offsetHeight;
                n({width: i, height: a})
            });
            return r.observe(e, {box: "border-box"}), () => r.unobserve(e)
        } else n(void 0)
    }, [e]), t
}

var Zh = "Popper", [j1, eo] = mn(Zh), [yM, P1] = j1(Zh), T1 = e => {
    const {__scopePopper: t, children: n} = e, [r, o] = g.useState(null);
    return c.jsx(yM, {scope: t, anchor: r, onAnchorChange: o, children: n})
};
T1.displayName = Zh;
var M1 = "PopperAnchor", R1 = g.forwardRef((e, t) => {
    const {__scopePopper: n, virtualRef: r, ...o} = e, s = P1(M1, n), i = g.useRef(null), a = ge(t, i);
    return g.useEffect(() => {
        s.onAnchorChange((r == null ? void 0 : r.current) || i.current)
    }), r ? null : c.jsx(re.div, {...o, ref: a})
});
R1.displayName = M1;
var Jh = "PopperContent", [xM, wM] = j1(Jh), A1 = g.forwardRef((e, t) => {
    var F, Y, X, te, ee, Q;
    const {
            __scopePopper: n,
            side: r = "bottom",
            sideOffset: o = 0,
            align: s = "center",
            alignOffset: i = 0,
            arrowPadding: a = 0,
            avoidCollisions: l = !0,
            collisionBoundary: u = [],
            collisionPadding: d = 0,
            sticky: f = "partial",
            hideWhenDetached: p = !1,
            updatePositionStrategy: h = "optimized",
            onPlaced: x,
            ...m
        } = e, b = P1(Jh, n), [y, w] = g.useState(null), v = ge(t, ue => w(ue)), [C, S] = g.useState(null), N = vM(C),
        E = (N == null ? void 0 : N.width) ?? 0, T = (N == null ? void 0 : N.height) ?? 0,
        O = r + (s !== "center" ? "-" + s : ""),
        D = typeof d == "number" ? d : {top: 0, right: 0, bottom: 0, left: 0, ...d}, L = Array.isArray(u) ? u : [u],
        $ = L.length > 0, B = {padding: D, boundary: L.filter(CM), altBoundary: $}, {
            refs: k,
            floatingStyles: M,
            placement: j,
            isPositioned: A,
            middlewareData: P
        } = iM({
            strategy: "fixed",
            placement: O,
            whileElementsMounted: (...ue) => ZT(...ue, {animationFrame: h === "always"}),
            elements: {reference: b.anchor},
            middleware: [lM({mainAxis: o + T, alignmentAxis: i}), l && cM({
                mainAxis: !0,
                crossAxis: !1,
                limiter: f === "partial" ? uM() : void 0, ...B
            }), l && dM({...B}), fM({
                ...B, apply: ({elements: ue, rects: ie, availableWidth: we, availableHeight: Ne}) => {
                    const {width: pt, height: $n} = ie.reference, yn = ue.floating.style;
                    yn.setProperty("--radix-popper-available-width", `${we}px`), yn.setProperty("--radix-popper-available-height", `${Ne}px`), yn.setProperty("--radix-popper-anchor-width", `${pt}px`), yn.setProperty("--radix-popper-anchor-height", `${$n}px`)
                }
            }), C && hM({element: C, padding: a}), SM({
                arrowWidth: E,
                arrowHeight: T
            }), p && pM({strategy: "referenceHidden", ...B})]
        }), [_, R] = D1(j), z = Xe(x);
    nt(() => {
        A && (z == null || z())
    }, [A, z]);
    const I = (F = P.arrow) == null ? void 0 : F.x, V = (Y = P.arrow) == null ? void 0 : Y.y,
        H = ((X = P.arrow) == null ? void 0 : X.centerOffset) !== 0, [U, K] = g.useState();
    return nt(() => {
        y && K(window.getComputedStyle(y).zIndex)
    }, [y]), c.jsx("div", {
        ref: k.setFloating,
        "data-radix-popper-content-wrapper": "",
        style: {
            ...M,
            transform: A ? M.transform : "translate(0, -200%)",
            minWidth: "max-content",
            zIndex: U,
            "--radix-popper-transform-origin": [(te = P.transformOrigin) == null ? void 0 : te.x, (ee = P.transformOrigin) == null ? void 0 : ee.y].join(" "), ...((Q = P.hide) == null ? void 0 : Q.referenceHidden) && {
                visibility: "hidden",
                pointerEvents: "none"
            }
        },
        dir: e.dir,
        children: c.jsx(xM, {
            scope: n,
            placedSide: _,
            onArrowChange: S,
            arrowX: I,
            arrowY: V,
            shouldHideArrow: H,
            children: c.jsx(re.div, {
                "data-side": _,
                "data-align": R, ...m,
                ref: v,
                style: {...m.style, animation: A ? void 0 : "none"}
            })
        })
    })
});
A1.displayName = Jh;
var I1 = "PopperArrow", bM = {top: "bottom", right: "left", bottom: "top", left: "right"},
    O1 = g.forwardRef(function (t, n) {
        const {__scopePopper: r, ...o} = t, s = wM(I1, r), i = bM[s.placedSide];
        return c.jsx("span", {
            ref: s.onArrowChange,
            style: {
                position: "absolute",
                left: s.arrowX,
                top: s.arrowY,
                [i]: 0,
                transformOrigin: {top: "", right: "0 0", bottom: "center 0", left: "100% 0"}[s.placedSide],
                transform: {
                    top: "translateY(100%)",
                    right: "translateY(50%) rotate(90deg) translateX(-50%)",
                    bottom: "rotate(180deg)",
                    left: "translateY(50%) rotate(-90deg) translateX(50%)"
                }[s.placedSide],
                visibility: s.shouldHideArrow ? "hidden" : void 0
            },
            children: c.jsx(gM, {...o, ref: n, style: {...o.style, display: "block"}})
        })
    });
O1.displayName = I1;

function CM(e) {
    return e !== null
}

var SM = e => ({
    name: "transformOrigin", options: e, fn(t) {
        var b, y, w;
        const {placement: n, rects: r, middlewareData: o} = t,
            i = ((b = o.arrow) == null ? void 0 : b.centerOffset) !== 0, a = i ? 0 : e.arrowWidth,
            l = i ? 0 : e.arrowHeight, [u, d] = D1(n), f = {start: "0%", center: "50%", end: "100%"}[d],
            p = (((y = o.arrow) == null ? void 0 : y.x) ?? 0) + a / 2,
            h = (((w = o.arrow) == null ? void 0 : w.y) ?? 0) + l / 2;
        let x = "", m = "";
        return u === "bottom" ? (x = i ? f : `${p}px`, m = `${-l}px`) : u === "top" ? (x = i ? f : `${p}px`, m = `${r.floating.height + l}px`) : u === "right" ? (x = `${-l}px`, m = i ? f : `${h}px`) : u === "left" && (x = `${r.floating.width + l}px`, m = i ? f : `${h}px`), {
            data: {
                x,
                y: m
            }
        }
    }
});

function D1(e) {
    const [t, n = "center"] = e.split("-");
    return [t, n]
}

var em = T1, Ma = R1, wu = A1, bu = O1, [Cu, yz] = mn("Tooltip", [eo]), tm = eo(), $1 = "TooltipProvider", NM = 700,
    Ev = "tooltip.open", [EM, L1] = Cu($1), F1 = e => {
        const {
            __scopeTooltip: t,
            delayDuration: n = NM,
            skipDelayDuration: r = 300,
            disableHoverableContent: o = !1,
            children: s
        } = e, i = g.useRef(!0), a = g.useRef(!1), l = g.useRef(0);
        return g.useEffect(() => {
            const u = l.current;
            return () => window.clearTimeout(u)
        }, []), c.jsx(EM, {
            scope: t, isOpenDelayedRef: i, delayDuration: n, onOpen: g.useCallback(() => {
                window.clearTimeout(l.current), i.current = !1
            }, []), onClose: g.useCallback(() => {
                window.clearTimeout(l.current), l.current = window.setTimeout(() => i.current = !0, r)
            }, [r]), isPointerInTransitRef: a, onPointerInTransitChange: g.useCallback(u => {
                a.current = u
            }, []), disableHoverableContent: o, children: s
        })
    };
F1.displayName = $1;
var z1 = "Tooltip", [xz, Su] = Cu(z1), dp = "TooltipTrigger", kM = g.forwardRef((e, t) => {
    const {__scopeTooltip: n, ...r} = e, o = Su(dp, n), s = L1(dp, n), i = tm(n), a = g.useRef(null),
        l = ge(t, a, o.onTriggerChange), u = g.useRef(!1), d = g.useRef(!1),
        f = g.useCallback(() => u.current = !1, []);
    return g.useEffect(() => () => document.removeEventListener("pointerup", f), [f]), c.jsx(Ma, {
        asChild: !0, ...i,
        children: c.jsx(re.button, {
            "aria-describedby": o.open ? o.contentId : void 0,
            "data-state": o.stateAttribute, ...r,
            ref: l,
            onPointerMove: J(e.onPointerMove, p => {
                p.pointerType !== "touch" && !d.current && !s.isPointerInTransitRef.current && (o.onTriggerEnter(), d.current = !0)
            }),
            onPointerLeave: J(e.onPointerLeave, () => {
                o.onTriggerLeave(), d.current = !1
            }),
            onPointerDown: J(e.onPointerDown, () => {
                o.open && o.onClose(), u.current = !0, document.addEventListener("pointerup", f, {once: !0})
            }),
            onFocus: J(e.onFocus, () => {
                u.current || o.onOpen()
            }),
            onBlur: J(e.onBlur, o.onClose),
            onClick: J(e.onClick, o.onClose)
        })
    })
});
kM.displayName = dp;
var _M = "TooltipPortal", [wz, jM] = Cu(_M, {forceMount: void 0}), Fs = "TooltipContent", B1 = g.forwardRef((e, t) => {
    const n = jM(Fs, e.__scopeTooltip), {forceMount: r = n.forceMount, side: o = "top", ...s} = e,
        i = Su(Fs, e.__scopeTooltip);
    return c.jsx(Dt, {
        present: r || i.open,
        children: i.disableHoverableContent ? c.jsx(V1, {side: o, ...s, ref: t}) : c.jsx(PM, {side: o, ...s, ref: t})
    })
}), PM = g.forwardRef((e, t) => {
    const n = Su(Fs, e.__scopeTooltip), r = L1(Fs, e.__scopeTooltip), o = g.useRef(null),
        s = ge(t, o), [i, a] = g.useState(null), {trigger: l, onClose: u} = n,
        d = o.current, {onPointerInTransitChange: f} = r, p = g.useCallback(() => {
            a(null), f(!1)
        }, [f]), h = g.useCallback((x, m) => {
            const b = x.currentTarget, y = {x: x.clientX, y: x.clientY}, w = IM(y, b.getBoundingClientRect()), v = OM(y, w),
                C = DM(m.getBoundingClientRect()), S = LM([...v, ...C]);
            a(S), f(!0)
        }, [f]);
    return g.useEffect(() => () => p(), [p]), g.useEffect(() => {
        if (l && d) {
            const x = b => h(b, d), m = b => h(b, l);
            return l.addEventListener("pointerleave", x), d.addEventListener("pointerleave", m), () => {
                l.removeEventListener("pointerleave", x), d.removeEventListener("pointerleave", m)
            }
        }
    }, [l, d, h, p]), g.useEffect(() => {
        if (i) {
            const x = m => {
                const b = m.target, y = {x: m.clientX, y: m.clientY},
                    w = (l == null ? void 0 : l.contains(b)) || (d == null ? void 0 : d.contains(b)), v = !$M(y, i);
                w ? p() : v && (p(), u())
            };
            return document.addEventListener("pointermove", x), () => document.removeEventListener("pointermove", x)
        }
    }, [l, d, i, u, p]), c.jsx(V1, {...e, ref: s})
}), [TM, MM] = Cu(z1, {isInside: !1}), RM = J_("TooltipContent"), V1 = g.forwardRef((e, t) => {
    const {__scopeTooltip: n, children: r, "aria-label": o, onEscapeKeyDown: s, onPointerDownOutside: i, ...a} = e,
        l = Su(Fs, n), u = tm(n), {onClose: d} = l;
    return g.useEffect(() => (document.addEventListener(Ev, d), () => document.removeEventListener(Ev, d)), [d]), g.useEffect(() => {
        if (l.trigger) {
            const f = p => {
                const h = p.target;
                h != null && h.contains(l.trigger) && d()
            };
            return window.addEventListener("scroll", f, {capture: !0}), () => window.removeEventListener("scroll", f, {capture: !0})
        }
    }, [l.trigger, d]), c.jsx(zo, {
        asChild: !0,
        disableOutsidePointerEvents: !1,
        onEscapeKeyDown: s,
        onPointerDownOutside: i,
        onFocusOutside: f => f.preventDefault(),
        onDismiss: d,
        children: c.jsxs(wu, {
            "data-state": l.stateAttribute, ...u, ...a,
            ref: t,
            style: {
                ...a.style,
                "--radix-tooltip-content-transform-origin": "var(--radix-popper-transform-origin)",
                "--radix-tooltip-content-available-width": "var(--radix-popper-available-width)",
                "--radix-tooltip-content-available-height": "var(--radix-popper-available-height)",
                "--radix-tooltip-trigger-width": "var(--radix-popper-anchor-width)",
                "--radix-tooltip-trigger-height": "var(--radix-popper-anchor-height)"
            },
            children: [c.jsx(RM, {children: r}), c.jsx(TM, {
                scope: n,
                isInside: !0,
                children: c.jsx(xj, {id: l.contentId, role: "tooltip", children: o || r})
            })]
        })
    })
});
B1.displayName = Fs;
var H1 = "TooltipArrow", AM = g.forwardRef((e, t) => {
    const {__scopeTooltip: n, ...r} = e, o = tm(n);
    return MM(H1, n).isInside ? null : c.jsx(bu, {...o, ...r, ref: t})
});
AM.displayName = H1;

function IM(e, t) {
    const n = Math.abs(t.top - e.y), r = Math.abs(t.bottom - e.y), o = Math.abs(t.right - e.x),
        s = Math.abs(t.left - e.x);
    switch (Math.min(n, r, o, s)) {
        case s:
            return "left";
        case o:
            return "right";
        case n:
            return "top";
        case r:
            return "bottom";
        default:
            throw new Error("unreachable")
    }
}

function OM(e, t, n = 5) {
    const r = [];
    switch (t) {
        case"top":
            r.push({x: e.x - n, y: e.y + n}, {x: e.x + n, y: e.y + n});
            break;
        case"bottom":
            r.push({x: e.x - n, y: e.y - n}, {x: e.x + n, y: e.y - n});
            break;
        case"left":
            r.push({x: e.x + n, y: e.y - n}, {x: e.x + n, y: e.y + n});
            break;
        case"right":
            r.push({x: e.x - n, y: e.y - n}, {x: e.x - n, y: e.y + n});
            break
    }
    return r
}

function DM(e) {
    const {top: t, right: n, bottom: r, left: o} = e;
    return [{x: o, y: t}, {x: n, y: t}, {x: n, y: r}, {x: o, y: r}]
}

function $M(e, t) {
    const {x: n, y: r} = e;
    let o = !1;
    for (let s = 0, i = t.length - 1; s < t.length; i = s++) {
        const a = t[s].x, l = t[s].y, u = t[i].x, d = t[i].y;
        l > r != d > r && n < (u - a) * (r - l) / (d - l) + a && (o = !o)
    }
    return o
}

function LM(e) {
    const t = e.slice();
    return t.sort((n, r) => n.x < r.x ? -1 : n.x > r.x ? 1 : n.y < r.y ? -1 : n.y > r.y ? 1 : 0), FM(t)
}

function FM(e) {
    if (e.length <= 1) return e.slice();
    const t = [];
    for (let r = 0; r < e.length; r++) {
        const o = e[r];
        for (; t.length >= 2;) {
            const s = t[t.length - 1], i = t[t.length - 2];
            if ((s.x - i.x) * (o.y - i.y) >= (s.y - i.y) * (o.x - i.x)) t.pop(); else break
        }
        t.push(o)
    }
    t.pop();
    const n = [];
    for (let r = e.length - 1; r >= 0; r--) {
        const o = e[r];
        for (; n.length >= 2;) {
            const s = n[n.length - 1], i = n[n.length - 2];
            if ((s.x - i.x) * (o.y - i.y) >= (s.y - i.y) * (o.x - i.x)) n.pop(); else break
        }
        n.push(o)
    }
    return n.pop(), t.length === 1 && n.length === 1 && t[0].x === n[0].x && t[0].y === n[0].y ? t : t.concat(n)
}

var zM = F1, U1 = B1;
const BM = zM, VM = g.forwardRef(({className: e, sideOffset: t = 4, ...n}, r) => c.jsx(U1, {
    ref: r,
    sideOffset: t,
    className: se("z-50 overflow-hidden rounded-md border bg-popover px-3 py-1.5 text-sm text-popover-foreground shadow-md animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2", e), ...n
}));
VM.displayName = U1.displayName;
var Nu = class {
    constructor() {
        this.listeners = new Set, this.subscribe = this.subscribe.bind(this)
    }

    subscribe(e) {
        return this.listeners.add(e), this.onSubscribe(), () => {
            this.listeners.delete(e), this.onUnsubscribe()
        }
    }

    hasListeners() {
        return this.listeners.size > 0
    }

    onSubscribe() {
    }

    onUnsubscribe() {
    }
}, Eu = typeof window > "u" || "Deno" in globalThis;

function Jt() {
}

function HM(e, t) {
    return typeof e == "function" ? e(t) : e
}

function UM(e) {
    return typeof e == "number" && e >= 0 && e !== 1 / 0
}

function WM(e, t) {
    return Math.max(e + (t || 0) - Date.now(), 0)
}

function kv(e, t) {
    return typeof e == "function" ? e(t) : e
}

function KM(e, t) {
    return typeof e == "function" ? e(t) : e
}

function _v(e, t) {
    const {type: n = "all", exact: r, fetchStatus: o, predicate: s, queryKey: i, stale: a} = e;
    if (i) {
        if (r) {
            if (t.queryHash !== nm(i, t.options)) return !1
        } else if (!aa(t.queryKey, i)) return !1
    }
    if (n !== "all") {
        const l = t.isActive();
        if (n === "active" && !l || n === "inactive" && l) return !1
    }
    return !(typeof a == "boolean" && t.isStale() !== a || o && o !== t.state.fetchStatus || s && !s(t))
}

function jv(e, t) {
    const {exact: n, status: r, predicate: o, mutationKey: s} = e;
    if (s) {
        if (!t.options.mutationKey) return !1;
        if (n) {
            if (ia(t.options.mutationKey) !== ia(s)) return !1
        } else if (!aa(t.options.mutationKey, s)) return !1
    }
    return !(r && t.state.status !== r || o && !o(t))
}

function nm(e, t) {
    return ((t == null ? void 0 : t.queryKeyHashFn) || ia)(e)
}

function ia(e) {
    return JSON.stringify(e, (t, n) => fp(n) ? Object.keys(n).sort().reduce((r, o) => (r[o] = n[o], r), {}) : n)
}

function aa(e, t) {
    return e === t ? !0 : typeof e != typeof t ? !1 : e && t && typeof e == "object" && typeof t == "object" ? Object.keys(t).every(n => aa(e[n], t[n])) : !1
}

function W1(e, t) {
    if (e === t) return e;
    const n = Pv(e) && Pv(t);
    if (n || fp(e) && fp(t)) {
        const r = n ? e : Object.keys(e), o = r.length, s = n ? t : Object.keys(t), i = s.length, a = n ? [] : {};
        let l = 0;
        for (let u = 0; u < i; u++) {
            const d = n ? u : s[u];
            (!n && r.includes(d) || n) && e[d] === void 0 && t[d] === void 0 ? (a[d] = void 0, l++) : (a[d] = W1(e[d], t[d]), a[d] === e[d] && e[d] !== void 0 && l++)
        }
        return o === i && l === o ? e : a
    }
    return t
}

function Pv(e) {
    return Array.isArray(e) && e.length === Object.keys(e).length
}

function fp(e) {
    if (!Tv(e)) return !1;
    const t = e.constructor;
    if (t === void 0) return !0;
    const n = t.prototype;
    return !(!Tv(n) || !n.hasOwnProperty("isPrototypeOf") || Object.getPrototypeOf(e) !== Object.prototype)
}

function Tv(e) {
    return Object.prototype.toString.call(e) === "[object Object]"
}

function YM(e) {
    return new Promise(t => {
        setTimeout(t, e)
    })
}

function GM(e, t, n) {
    return typeof n.structuralSharing == "function" ? n.structuralSharing(e, t) : n.structuralSharing !== !1 ? W1(e, t) : t
}

function XM(e, t, n = 0) {
    const r = [...e, t];
    return n && r.length > n ? r.slice(1) : r
}

function qM(e, t, n = 0) {
    const r = [t, ...e];
    return n && r.length > n ? r.slice(0, -1) : r
}

var rm = Symbol();

function K1(e, t) {
    return !e.queryFn && (t != null && t.initialPromise) ? () => t.initialPromise : !e.queryFn || e.queryFn === rm ? () => Promise.reject(new Error(`Missing queryFn: '${e.queryHash}'`)) : e.queryFn
}

var ho, _r, Cs, J0, QM = (J0 = class extends Nu {
    constructor() {
        super();
        ve(this, ho);
        ve(this, _r);
        ve(this, Cs);
        ae(this, Cs, t => {
            if (!Eu && window.addEventListener) {
                const n = () => t();
                return window.addEventListener("visibilitychange", n, !1), () => {
                    window.removeEventListener("visibilitychange", n)
                }
            }
        })
    }

    onSubscribe() {
        W(this, _r) || this.setEventListener(W(this, Cs))
    }

    onUnsubscribe() {
        var t;
        this.hasListeners() || ((t = W(this, _r)) == null || t.call(this), ae(this, _r, void 0))
    }

    setEventListener(t) {
        var n;
        ae(this, Cs, t), (n = W(this, _r)) == null || n.call(this), ae(this, _r, t(r => {
            typeof r == "boolean" ? this.setFocused(r) : this.onFocus()
        }))
    }

    setFocused(t) {
        W(this, ho) !== t && (ae(this, ho, t), this.onFocus())
    }

    onFocus() {
        const t = this.isFocused();
        this.listeners.forEach(n => {
            n(t)
        })
    }

    isFocused() {
        var t;
        return typeof W(this, ho) == "boolean" ? W(this, ho) : ((t = globalThis.document) == null ? void 0 : t.visibilityState) !== "hidden"
    }
}, ho = new WeakMap, _r = new WeakMap, Cs = new WeakMap, J0), Y1 = new QM, Ss, jr, Ns, ey, ZM = (ey = class extends Nu {
    constructor() {
        super();
        ve(this, Ss, !0);
        ve(this, jr);
        ve(this, Ns);
        ae(this, Ns, t => {
            if (!Eu && window.addEventListener) {
                const n = () => t(!0), r = () => t(!1);
                return window.addEventListener("online", n, !1), window.addEventListener("offline", r, !1), () => {
                    window.removeEventListener("online", n), window.removeEventListener("offline", r)
                }
            }
        })
    }

    onSubscribe() {
        W(this, jr) || this.setEventListener(W(this, Ns))
    }

    onUnsubscribe() {
        var t;
        this.hasListeners() || ((t = W(this, jr)) == null || t.call(this), ae(this, jr, void 0))
    }

    setEventListener(t) {
        var n;
        ae(this, Ns, t), (n = W(this, jr)) == null || n.call(this), ae(this, jr, t(this.setOnline.bind(this)))
    }

    setOnline(t) {
        W(this, Ss) !== t && (ae(this, Ss, t), this.listeners.forEach(r => {
            r(t)
        }))
    }

    isOnline() {
        return W(this, Ss)
    }
}, Ss = new WeakMap, jr = new WeakMap, Ns = new WeakMap, ey), Ac = new ZM;

function JM() {
    let e, t;
    const n = new Promise((o, s) => {
        e = o, t = s
    });
    n.status = "pending", n.catch(() => {
    });

    function r(o) {
        Object.assign(n, o), delete n.resolve, delete n.reject
    }

    return n.resolve = o => {
        r({status: "fulfilled", value: o}), e(o)
    }, n.reject = o => {
        r({status: "rejected", reason: o}), t(o)
    }, n
}

function eR(e) {
    return Math.min(1e3 * 2 ** e, 3e4)
}

function G1(e) {
    return (e ?? "online") === "online" ? Ac.isOnline() : !0
}

var X1 = class extends Error {
    constructor(e) {
        super("CancelledError"), this.revert = e == null ? void 0 : e.revert, this.silent = e == null ? void 0 : e.silent
    }
};

function zd(e) {
    return e instanceof X1
}

function q1(e) {
    let t = !1, n = 0, r = !1, o;
    const s = JM(), i = m => {
            var b;
            r || (p(new X1(m)), (b = e.abort) == null || b.call(e))
        }, a = () => {
            t = !0
        }, l = () => {
            t = !1
        }, u = () => Y1.isFocused() && (e.networkMode === "always" || Ac.isOnline()) && e.canRun(),
        d = () => G1(e.networkMode) && e.canRun(), f = m => {
            var b;
            r || (r = !0, (b = e.onSuccess) == null || b.call(e, m), o == null || o(), s.resolve(m))
        }, p = m => {
            var b;
            r || (r = !0, (b = e.onError) == null || b.call(e, m), o == null || o(), s.reject(m))
        }, h = () => new Promise(m => {
            var b;
            o = y => {
                (r || u()) && m(y)
            }, (b = e.onPause) == null || b.call(e)
        }).then(() => {
            var m;
            o = void 0, r || (m = e.onContinue) == null || m.call(e)
        }), x = () => {
            if (r) return;
            let m;
            const b = n === 0 ? e.initialPromise : void 0;
            try {
                m = b ?? e.fn()
            } catch (y) {
                m = Promise.reject(y)
            }
            Promise.resolve(m).then(f).catch(y => {
                var N;
                if (r) return;
                const w = e.retry ?? (Eu ? 0 : 3), v = e.retryDelay ?? eR, C = typeof v == "function" ? v(n, y) : v,
                    S = w === !0 || typeof w == "number" && n < w || typeof w == "function" && w(n, y);
                if (t || !S) {
                    p(y);
                    return
                }
                n++, (N = e.onFail) == null || N.call(e, n, y), YM(C).then(() => u() ? void 0 : h()).then(() => {
                    t ? p(y) : x()
                })
            })
        };
    return {
        promise: s,
        cancel: i,
        continue: () => (o == null || o(), s),
        cancelRetry: a,
        continueRetry: l,
        canStart: d,
        start: () => (d() ? x() : h().then(x), s)
    }
}

var tR = e => setTimeout(e, 0);

function nR() {
    let e = [], t = 0, n = a => {
        a()
    }, r = a => {
        a()
    }, o = tR;
    const s = a => {
        t ? e.push(a) : o(() => {
            n(a)
        })
    }, i = () => {
        const a = e;
        e = [], a.length && o(() => {
            r(() => {
                a.forEach(l => {
                    n(l)
                })
            })
        })
    };
    return {
        batch: a => {
            let l;
            t++;
            try {
                l = a()
            } finally {
                t--, t || i()
            }
            return l
        }, batchCalls: a => (...l) => {
            s(() => {
                a(...l)
            })
        }, schedule: s, setNotifyFunction: a => {
            n = a
        }, setBatchNotifyFunction: a => {
            r = a
        }, setScheduler: a => {
            o = a
        }
    }
}

var it = nR(), mo, ty, Q1 = (ty = class {
    constructor() {
        ve(this, mo)
    }

    destroy() {
        this.clearGcTimeout()
    }

    scheduleGc() {
        this.clearGcTimeout(), UM(this.gcTime) && ae(this, mo, setTimeout(() => {
            this.optionalRemove()
        }, this.gcTime))
    }

    updateGcTime(e) {
        this.gcTime = Math.max(this.gcTime || 0, e ?? (Eu ? 1 / 0 : 5 * 60 * 1e3))
    }

    clearGcTimeout() {
        W(this, mo) && (clearTimeout(W(this, mo)), ae(this, mo, void 0))
    }
}, mo = new WeakMap, ty), Es, ks, Lt, go, Je, wa, vo, en, Vn, ny, rR = (ny = class extends Q1 {
    constructor(t) {
        super();
        ve(this, en);
        ve(this, Es);
        ve(this, ks);
        ve(this, Lt);
        ve(this, go);
        ve(this, Je);
        ve(this, wa);
        ve(this, vo);
        ae(this, vo, !1), ae(this, wa, t.defaultOptions), this.setOptions(t.options), this.observers = [], ae(this, go, t.client), ae(this, Lt, W(this, go).getQueryCache()), this.queryKey = t.queryKey, this.queryHash = t.queryHash, ae(this, Es, sR(this.options)), this.state = t.state ?? W(this, Es), this.scheduleGc()
    }

    get meta() {
        return this.options.meta
    }

    get promise() {
        var t;
        return (t = W(this, Je)) == null ? void 0 : t.promise
    }

    setOptions(t) {
        this.options = {...W(this, wa), ...t}, this.updateGcTime(this.options.gcTime)
    }

    optionalRemove() {
        !this.observers.length && this.state.fetchStatus === "idle" && W(this, Lt).remove(this)
    }

    setData(t, n) {
        const r = GM(this.state.data, t, this.options);
        return qe(this, en, Vn).call(this, {
            data: r,
            type: "success",
            dataUpdatedAt: n == null ? void 0 : n.updatedAt,
            manual: n == null ? void 0 : n.manual
        }), r
    }

    setState(t, n) {
        qe(this, en, Vn).call(this, {type: "setState", state: t, setStateOptions: n})
    }

    cancel(t) {
        var r, o;
        const n = (r = W(this, Je)) == null ? void 0 : r.promise;
        return (o = W(this, Je)) == null || o.cancel(t), n ? n.then(Jt).catch(Jt) : Promise.resolve()
    }

    destroy() {
        super.destroy(), this.cancel({silent: !0})
    }

    reset() {
        this.destroy(), this.setState(W(this, Es))
    }

    isActive() {
        return this.observers.some(t => KM(t.options.enabled, this) !== !1)
    }

    isDisabled() {
        return this.getObserversCount() > 0 ? !this.isActive() : this.options.queryFn === rm || this.state.dataUpdateCount + this.state.errorUpdateCount === 0
    }

    isStale() {
        return this.state.isInvalidated ? !0 : this.getObserversCount() > 0 ? this.observers.some(t => t.getCurrentResult().isStale) : this.state.data === void 0
    }

    isStaleByTime(t = 0) {
        return this.state.isInvalidated || this.state.data === void 0 || !WM(this.state.dataUpdatedAt, t)
    }

    onFocus() {
        var n;
        const t = this.observers.find(r => r.shouldFetchOnWindowFocus());
        t == null || t.refetch({cancelRefetch: !1}), (n = W(this, Je)) == null || n.continue()
    }

    onOnline() {
        var n;
        const t = this.observers.find(r => r.shouldFetchOnReconnect());
        t == null || t.refetch({cancelRefetch: !1}), (n = W(this, Je)) == null || n.continue()
    }

    addObserver(t) {
        this.observers.includes(t) || (this.observers.push(t), this.clearGcTimeout(), W(this, Lt).notify({
            type: "observerAdded",
            query: this,
            observer: t
        }))
    }

    removeObserver(t) {
        this.observers.includes(t) && (this.observers = this.observers.filter(n => n !== t), this.observers.length || (W(this, Je) && (W(this, vo) ? W(this, Je).cancel({revert: !0}) : W(this, Je).cancelRetry()), this.scheduleGc()), W(this, Lt).notify({
            type: "observerRemoved",
            query: this,
            observer: t
        }))
    }

    getObserversCount() {
        return this.observers.length
    }

    invalidate() {
        this.state.isInvalidated || qe(this, en, Vn).call(this, {type: "invalidate"})
    }

    fetch(t, n) {
        var l, u, d;
        if (this.state.fetchStatus !== "idle") {
            if (this.state.data !== void 0 && (n != null && n.cancelRefetch)) this.cancel({silent: !0}); else if (W(this, Je)) return W(this, Je).continueRetry(), W(this, Je).promise
        }
        if (t && this.setOptions(t), !this.options.queryFn) {
            const f = this.observers.find(p => p.options.queryFn);
            f && this.setOptions(f.options)
        }
        const r = new AbortController, o = f => {
            Object.defineProperty(f, "signal", {enumerable: !0, get: () => (ae(this, vo, !0), r.signal)})
        }, s = () => {
            const f = K1(this.options, n), p = {client: W(this, go), queryKey: this.queryKey, meta: this.meta};
            return o(p), ae(this, vo, !1), this.options.persister ? this.options.persister(f, p, this) : f(p)
        }, i = {
            fetchOptions: n,
            options: this.options,
            queryKey: this.queryKey,
            client: W(this, go),
            state: this.state,
            fetchFn: s
        };
        o(i), (l = this.options.behavior) == null || l.onFetch(i, this), ae(this, ks, this.state), (this.state.fetchStatus === "idle" || this.state.fetchMeta !== ((u = i.fetchOptions) == null ? void 0 : u.meta)) && qe(this, en, Vn).call(this, {
            type: "fetch",
            meta: (d = i.fetchOptions) == null ? void 0 : d.meta
        });
        const a = f => {
            var p, h, x, m;
            zd(f) && f.silent || qe(this, en, Vn).call(this, {
                type: "error",
                error: f
            }), zd(f) || ((h = (p = W(this, Lt).config).onError) == null || h.call(p, f, this), (m = (x = W(this, Lt).config).onSettled) == null || m.call(x, this.state.data, f, this)), this.scheduleGc()
        };
        return ae(this, Je, q1({
            initialPromise: n == null ? void 0 : n.initialPromise,
            fn: i.fetchFn,
            abort: r.abort.bind(r),
            onSuccess: f => {
                var p, h, x, m;
                if (f === void 0) {
                    a(new Error(`${this.queryHash} data is undefined`));
                    return
                }
                try {
                    this.setData(f)
                } catch (b) {
                    a(b);
                    return
                }
                (h = (p = W(this, Lt).config).onSuccess) == null || h.call(p, f, this), (m = (x = W(this, Lt).config).onSettled) == null || m.call(x, f, this.state.error, this), this.scheduleGc()
            },
            onError: a,
            onFail: (f, p) => {
                qe(this, en, Vn).call(this, {type: "failed", failureCount: f, error: p})
            },
            onPause: () => {
                qe(this, en, Vn).call(this, {type: "pause"})
            },
            onContinue: () => {
                qe(this, en, Vn).call(this, {type: "continue"})
            },
            retry: i.options.retry,
            retryDelay: i.options.retryDelay,
            networkMode: i.options.networkMode,
            canRun: () => !0
        })), W(this, Je).start()
    }
}, Es = new WeakMap, ks = new WeakMap, Lt = new WeakMap, go = new WeakMap, Je = new WeakMap, wa = new WeakMap, vo = new WeakMap, en = new WeakSet, Vn = function (t) {
    const n = r => {
        switch (t.type) {
            case"failed":
                return {...r, fetchFailureCount: t.failureCount, fetchFailureReason: t.error};
            case"pause":
                return {...r, fetchStatus: "paused"};
            case"continue":
                return {...r, fetchStatus: "fetching"};
            case"fetch":
                return {...r, ...oR(r.data, this.options), fetchMeta: t.meta ?? null};
            case"success":
                return {
                    ...r,
                    data: t.data,
                    dataUpdateCount: r.dataUpdateCount + 1,
                    dataUpdatedAt: t.dataUpdatedAt ?? Date.now(),
                    error: null,
                    isInvalidated: !1,
                    status: "success", ...!t.manual && {
                        fetchStatus: "idle",
                        fetchFailureCount: 0,
                        fetchFailureReason: null
                    }
                };
            case"error":
                const o = t.error;
                return zd(o) && o.revert && W(this, ks) ? {...W(this, ks), fetchStatus: "idle"} : {
                    ...r,
                    error: o,
                    errorUpdateCount: r.errorUpdateCount + 1,
                    errorUpdatedAt: Date.now(),
                    fetchFailureCount: r.fetchFailureCount + 1,
                    fetchFailureReason: o,
                    fetchStatus: "idle",
                    status: "error"
                };
            case"invalidate":
                return {...r, isInvalidated: !0};
            case"setState":
                return {...r, ...t.state}
        }
    };
    this.state = n(this.state), it.batch(() => {
        this.observers.forEach(r => {
            r.onQueryUpdate()
        }), W(this, Lt).notify({query: this, type: "updated", action: t})
    })
}, ny);

function oR(e, t) {
    return {
        fetchFailureCount: 0,
        fetchFailureReason: null,
        fetchStatus: G1(t.networkMode) ? "fetching" : "paused", ...e === void 0 && {error: null, status: "pending"}
    }
}

function sR(e) {
    const t = typeof e.initialData == "function" ? e.initialData() : e.initialData, n = t !== void 0,
        r = n ? typeof e.initialDataUpdatedAt == "function" ? e.initialDataUpdatedAt() : e.initialDataUpdatedAt : 0;
    return {
        data: t,
        dataUpdateCount: 0,
        dataUpdatedAt: n ? r ?? Date.now() : 0,
        error: null,
        errorUpdateCount: 0,
        errorUpdatedAt: 0,
        fetchFailureCount: 0,
        fetchFailureReason: null,
        fetchMeta: null,
        isInvalidated: !1,
        status: n ? "success" : "pending",
        fetchStatus: "idle"
    }
}

var Nn, ry, iR = (ry = class extends Nu {
    constructor(t = {}) {
        super();
        ve(this, Nn);
        this.config = t, ae(this, Nn, new Map)
    }

    build(t, n, r) {
        const o = n.queryKey, s = n.queryHash ?? nm(o, n);
        let i = this.get(s);
        return i || (i = new rR({
            client: t,
            queryKey: o,
            queryHash: s,
            options: t.defaultQueryOptions(n),
            state: r,
            defaultOptions: t.getQueryDefaults(o)
        }), this.add(i)), i
    }

    add(t) {
        W(this, Nn).has(t.queryHash) || (W(this, Nn).set(t.queryHash, t), this.notify({type: "added", query: t}))
    }

    remove(t) {
        const n = W(this, Nn).get(t.queryHash);
        n && (t.destroy(), n === t && W(this, Nn).delete(t.queryHash), this.notify({type: "removed", query: t}))
    }

    clear() {
        it.batch(() => {
            this.getAll().forEach(t => {
                this.remove(t)
            })
        })
    }

    get(t) {
        return W(this, Nn).get(t)
    }

    getAll() {
        return [...W(this, Nn).values()]
    }

    find(t) {
        const n = {exact: !0, ...t};
        return this.getAll().find(r => _v(n, r))
    }

    findAll(t = {}) {
        const n = this.getAll();
        return Object.keys(t).length > 0 ? n.filter(r => _v(t, r)) : n
    }

    notify(t) {
        it.batch(() => {
            this.listeners.forEach(n => {
                n(t)
            })
        })
    }

    onFocus() {
        it.batch(() => {
            this.getAll().forEach(t => {
                t.onFocus()
            })
        })
    }

    onOnline() {
        it.batch(() => {
            this.getAll().forEach(t => {
                t.onOnline()
            })
        })
    }
}, Nn = new WeakMap, ry), En, ot, yo, kn, xr, oy, aR = (oy = class extends Q1 {
    constructor(t) {
        super();
        ve(this, kn);
        ve(this, En);
        ve(this, ot);
        ve(this, yo);
        this.mutationId = t.mutationId, ae(this, ot, t.mutationCache), ae(this, En, []), this.state = t.state || lR(), this.setOptions(t.options), this.scheduleGc()
    }

    setOptions(t) {
        this.options = t, this.updateGcTime(this.options.gcTime)
    }

    get meta() {
        return this.options.meta
    }

    addObserver(t) {
        W(this, En).includes(t) || (W(this, En).push(t), this.clearGcTimeout(), W(this, ot).notify({
            type: "observerAdded",
            mutation: this,
            observer: t
        }))
    }

    removeObserver(t) {
        ae(this, En, W(this, En).filter(n => n !== t)), this.scheduleGc(), W(this, ot).notify({
            type: "observerRemoved",
            mutation: this,
            observer: t
        })
    }

    optionalRemove() {
        W(this, En).length || (this.state.status === "pending" ? this.scheduleGc() : W(this, ot).remove(this))
    }

    continue() {
        var t;
        return ((t = W(this, yo)) == null ? void 0 : t.continue()) ?? this.execute(this.state.variables)
    }

    async execute(t) {
        var s, i, a, l, u, d, f, p, h, x, m, b, y, w, v, C, S, N, E, T;
        const n = () => {
            qe(this, kn, xr).call(this, {type: "continue"})
        };
        ae(this, yo, q1({
            fn: () => this.options.mutationFn ? this.options.mutationFn(t) : Promise.reject(new Error("No mutationFn found")),
            onFail: (O, D) => {
                qe(this, kn, xr).call(this, {type: "failed", failureCount: O, error: D})
            },
            onPause: () => {
                qe(this, kn, xr).call(this, {type: "pause"})
            },
            onContinue: n,
            retry: this.options.retry ?? 0,
            retryDelay: this.options.retryDelay,
            networkMode: this.options.networkMode,
            canRun: () => W(this, ot).canRun(this)
        }));
        const r = this.state.status === "pending", o = !W(this, yo).canStart();
        try {
            if (r) n(); else {
                qe(this, kn, xr).call(this, {
                    type: "pending",
                    variables: t,
                    isPaused: o
                }), await ((i = (s = W(this, ot).config).onMutate) == null ? void 0 : i.call(s, t, this));
                const D = await ((l = (a = this.options).onMutate) == null ? void 0 : l.call(a, t));
                D !== this.state.context && qe(this, kn, xr).call(this, {
                    type: "pending",
                    context: D,
                    variables: t,
                    isPaused: o
                })
            }
            const O = await W(this, yo).start();
            return await ((d = (u = W(this, ot).config).onSuccess) == null ? void 0 : d.call(u, O, t, this.state.context, this)), await ((p = (f = this.options).onSuccess) == null ? void 0 : p.call(f, O, t, this.state.context)), await ((x = (h = W(this, ot).config).onSettled) == null ? void 0 : x.call(h, O, null, this.state.variables, this.state.context, this)), await ((b = (m = this.options).onSettled) == null ? void 0 : b.call(m, O, null, t, this.state.context)), qe(this, kn, xr).call(this, {
                type: "success",
                data: O
            }), O
        } catch (O) {
            try {
                throw await ((w = (y = W(this, ot).config).onError) == null ? void 0 : w.call(y, O, t, this.state.context, this)), await ((C = (v = this.options).onError) == null ? void 0 : C.call(v, O, t, this.state.context)), await ((N = (S = W(this, ot).config).onSettled) == null ? void 0 : N.call(S, void 0, O, this.state.variables, this.state.context, this)), await ((T = (E = this.options).onSettled) == null ? void 0 : T.call(E, void 0, O, t, this.state.context)), O
            } finally {
                qe(this, kn, xr).call(this, {type: "error", error: O})
            }
        } finally {
            W(this, ot).runNext(this)
        }
    }
}, En = new WeakMap, ot = new WeakMap, yo = new WeakMap, kn = new WeakSet, xr = function (t) {
    const n = r => {
        switch (t.type) {
            case"failed":
                return {...r, failureCount: t.failureCount, failureReason: t.error};
            case"pause":
                return {...r, isPaused: !0};
            case"continue":
                return {...r, isPaused: !1};
            case"pending":
                return {
                    ...r,
                    context: t.context,
                    data: void 0,
                    failureCount: 0,
                    failureReason: null,
                    error: null,
                    isPaused: t.isPaused,
                    status: "pending",
                    variables: t.variables,
                    submittedAt: Date.now()
                };
            case"success":
                return {
                    ...r,
                    data: t.data,
                    failureCount: 0,
                    failureReason: null,
                    error: null,
                    status: "success",
                    isPaused: !1
                };
            case"error":
                return {
                    ...r,
                    data: void 0,
                    error: t.error,
                    failureCount: r.failureCount + 1,
                    failureReason: t.error,
                    isPaused: !1,
                    status: "error"
                }
        }
    };
    this.state = n(this.state), it.batch(() => {
        W(this, En).forEach(r => {
            r.onMutationUpdate(t)
        }), W(this, ot).notify({mutation: this, type: "updated", action: t})
    })
}, oy);

function lR() {
    return {
        context: void 0,
        data: void 0,
        error: null,
        failureCount: 0,
        failureReason: null,
        isPaused: !1,
        status: "idle",
        variables: void 0,
        submittedAt: 0
    }
}

var Wn, tn, ba, sy, cR = (sy = class extends Nu {
    constructor(t = {}) {
        super();
        ve(this, Wn);
        ve(this, tn);
        ve(this, ba);
        this.config = t, ae(this, Wn, new Set), ae(this, tn, new Map), ae(this, ba, 0)
    }

    build(t, n, r) {
        const o = new aR({
            mutationCache: this,
            mutationId: ++el(this, ba)._,
            options: t.defaultMutationOptions(n),
            state: r
        });
        return this.add(o), o
    }

    add(t) {
        W(this, Wn).add(t);
        const n = Cl(t);
        if (typeof n == "string") {
            const r = W(this, tn).get(n);
            r ? r.push(t) : W(this, tn).set(n, [t])
        }
        this.notify({type: "added", mutation: t})
    }

    remove(t) {
        if (W(this, Wn).delete(t)) {
            const n = Cl(t);
            if (typeof n == "string") {
                const r = W(this, tn).get(n);
                if (r) if (r.length > 1) {
                    const o = r.indexOf(t);
                    o !== -1 && r.splice(o, 1)
                } else r[0] === t && W(this, tn).delete(n)
            }
        }
        this.notify({type: "removed", mutation: t})
    }

    canRun(t) {
        const n = Cl(t);
        if (typeof n == "string") {
            const r = W(this, tn).get(n), o = r == null ? void 0 : r.find(s => s.state.status === "pending");
            return !o || o === t
        } else return !0
    }

    runNext(t) {
        var r;
        const n = Cl(t);
        if (typeof n == "string") {
            const o = (r = W(this, tn).get(n)) == null ? void 0 : r.find(s => s !== t && s.state.isPaused);
            return (o == null ? void 0 : o.continue()) ?? Promise.resolve()
        } else return Promise.resolve()
    }

    clear() {
        it.batch(() => {
            W(this, Wn).forEach(t => {
                this.notify({type: "removed", mutation: t})
            }), W(this, Wn).clear(), W(this, tn).clear()
        })
    }

    getAll() {
        return Array.from(W(this, Wn))
    }

    find(t) {
        const n = {exact: !0, ...t};
        return this.getAll().find(r => jv(n, r))
    }

    findAll(t = {}) {
        return this.getAll().filter(n => jv(t, n))
    }

    notify(t) {
        it.batch(() => {
            this.listeners.forEach(n => {
                n(t)
            })
        })
    }

    resumePausedMutations() {
        const t = this.getAll().filter(n => n.state.isPaused);
        return it.batch(() => Promise.all(t.map(n => n.continue().catch(Jt))))
    }
}, Wn = new WeakMap, tn = new WeakMap, ba = new WeakMap, sy);

function Cl(e) {
    var t;
    return (t = e.options.scope) == null ? void 0 : t.id
}

function Mv(e) {
    return {
        onFetch: (t, n) => {
            var d, f, p, h, x;
            const r = t.options,
                o = (p = (f = (d = t.fetchOptions) == null ? void 0 : d.meta) == null ? void 0 : f.fetchMore) == null ? void 0 : p.direction,
                s = ((h = t.state.data) == null ? void 0 : h.pages) || [],
                i = ((x = t.state.data) == null ? void 0 : x.pageParams) || [];
            let a = {pages: [], pageParams: []}, l = 0;
            const u = async () => {
                let m = !1;
                const b = v => {
                    Object.defineProperty(v, "signal", {
                        enumerable: !0,
                        get: () => (t.signal.aborted ? m = !0 : t.signal.addEventListener("abort", () => {
                            m = !0
                        }), t.signal)
                    })
                }, y = K1(t.options, t.fetchOptions), w = async (v, C, S) => {
                    if (m) return Promise.reject();
                    if (C == null && v.pages.length) return Promise.resolve(v);
                    const N = {
                        client: t.client,
                        queryKey: t.queryKey,
                        pageParam: C,
                        direction: S ? "backward" : "forward",
                        meta: t.options.meta
                    };
                    b(N);
                    const E = await y(N), {maxPages: T} = t.options, O = S ? qM : XM;
                    return {pages: O(v.pages, E, T), pageParams: O(v.pageParams, C, T)}
                };
                if (o && s.length) {
                    const v = o === "backward", C = v ? uR : Rv, S = {pages: s, pageParams: i}, N = C(r, S);
                    a = await w(S, N, v)
                } else {
                    const v = e ?? s.length;
                    do {
                        const C = l === 0 ? i[0] ?? r.initialPageParam : Rv(r, a);
                        if (l > 0 && C == null) break;
                        a = await w(a, C), l++
                    } while (l < v)
                }
                return a
            };
            t.options.persister ? t.fetchFn = () => {
                var m, b;
                return (b = (m = t.options).persister) == null ? void 0 : b.call(m, u, {
                    client: t.client,
                    queryKey: t.queryKey,
                    meta: t.options.meta,
                    signal: t.signal
                }, n)
            } : t.fetchFn = u
        }
    }
}

function Rv(e, {pages: t, pageParams: n}) {
    const r = t.length - 1;
    return t.length > 0 ? e.getNextPageParam(t[r], t, n[r], n) : void 0
}

function uR(e, {pages: t, pageParams: n}) {
    var r;
    return t.length > 0 ? (r = e.getPreviousPageParam) == null ? void 0 : r.call(e, t[0], t, n[0], n) : void 0
}

var Ie, Pr, Tr, _s, js, Mr, Ps, Ts, iy, dR = (iy = class {
        constructor(e = {}) {
            ve(this, Ie);
            ve(this, Pr);
            ve(this, Tr);
            ve(this, _s);
            ve(this, js);
            ve(this, Mr);
            ve(this, Ps);
            ve(this, Ts);
            ae(this, Ie, e.queryCache || new iR), ae(this, Pr, e.mutationCache || new cR), ae(this, Tr, e.defaultOptions || {}), ae(this, _s, new Map), ae(this, js, new Map), ae(this, Mr, 0)
        }

        mount() {
            el(this, Mr)._++, W(this, Mr) === 1 && (ae(this, Ps, Y1.subscribe(async e => {
                e && (await this.resumePausedMutations(), W(this, Ie).onFocus())
            })), ae(this, Ts, Ac.subscribe(async e => {
                e && (await this.resumePausedMutations(), W(this, Ie).onOnline())
            })))
        }

        unmount() {
            var e, t;
            el(this, Mr)._--, W(this, Mr) === 0 && ((e = W(this, Ps)) == null || e.call(this), ae(this, Ps, void 0), (t = W(this, Ts)) == null || t.call(this), ae(this, Ts, void 0))
        }

        isFetching(e) {
            return W(this, Ie).findAll({...e, fetchStatus: "fetching"}).length
        }

        isMutating(e) {
            return W(this, Pr).findAll({...e, status: "pending"}).length
        }

        getQueryData(e) {
            var n;
            const t = this.defaultQueryOptions({queryKey: e});
            return (n = W(this, Ie).get(t.queryHash)) == null ? void 0 : n.state.data
        }

        ensureQueryData(e) {
            const t = this.defaultQueryOptions(e), n = W(this, Ie).build(this, t), r = n.state.data;
            return r === void 0 ? this.fetchQuery(e) : (e.revalidateIfStale && n.isStaleByTime(kv(t.staleTime, n)) && this.prefetchQuery(t), Promise.resolve(r))
        }

        getQueriesData(e) {
            return W(this, Ie).findAll(e).map(({queryKey: t, state: n}) => {
                const r = n.data;
                return [t, r]
            })
        }

        setQueryData(e, t, n) {
            const r = this.defaultQueryOptions({queryKey: e}), o = W(this, Ie).get(r.queryHash),
                s = o == null ? void 0 : o.state.data, i = HM(t, s);
            if (i !== void 0) return W(this, Ie).build(this, r).setData(i, {...n, manual: !0})
        }

        setQueriesData(e, t, n) {
            return it.batch(() => W(this, Ie).findAll(e).map(({queryKey: r}) => [r, this.setQueryData(r, t, n)]))
        }

        getQueryState(e) {
            var n;
            const t = this.defaultQueryOptions({queryKey: e});
            return (n = W(this, Ie).get(t.queryHash)) == null ? void 0 : n.state
        }

        removeQueries(e) {
            const t = W(this, Ie);
            it.batch(() => {
                t.findAll(e).forEach(n => {
                    t.remove(n)
                })
            })
        }

        resetQueries(e, t) {
            const n = W(this, Ie);
            return it.batch(() => (n.findAll(e).forEach(r => {
                r.reset()
            }), this.refetchQueries({type: "active", ...e}, t)))
        }

        cancelQueries(e, t = {}) {
            const n = {revert: !0, ...t}, r = it.batch(() => W(this, Ie).findAll(e).map(o => o.cancel(n)));
            return Promise.all(r).then(Jt).catch(Jt)
        }

        invalidateQueries(e, t = {}) {
            return it.batch(() => (W(this, Ie).findAll(e).forEach(n => {
                n.invalidate()
            }), (e == null ? void 0 : e.refetchType) === "none" ? Promise.resolve() : this.refetchQueries({
                ...e,
                type: (e == null ? void 0 : e.refetchType) ?? (e == null ? void 0 : e.type) ?? "active"
            }, t)))
        }

        refetchQueries(e, t = {}) {
            const n = {...t, cancelRefetch: t.cancelRefetch ?? !0},
                r = it.batch(() => W(this, Ie).findAll(e).filter(o => !o.isDisabled()).map(o => {
                    let s = o.fetch(void 0, n);
                    return n.throwOnError || (s = s.catch(Jt)), o.state.fetchStatus === "paused" ? Promise.resolve() : s
                }));
            return Promise.all(r).then(Jt)
        }

        fetchQuery(e) {
            const t = this.defaultQueryOptions(e);
            t.retry === void 0 && (t.retry = !1);
            const n = W(this, Ie).build(this, t);
            return n.isStaleByTime(kv(t.staleTime, n)) ? n.fetch(t) : Promise.resolve(n.state.data)
        }

        prefetchQuery(e) {
            return this.fetchQuery(e).then(Jt).catch(Jt)
        }

        fetchInfiniteQuery(e) {
            return e.behavior = Mv(e.pages), this.fetchQuery(e)
        }

        prefetchInfiniteQuery(e) {
            return this.fetchInfiniteQuery(e).then(Jt).catch(Jt)
        }

        ensureInfiniteQueryData(e) {
            return e.behavior = Mv(e.pages), this.ensureQueryData(e)
        }

        resumePausedMutations() {
            return Ac.isOnline() ? W(this, Pr).resumePausedMutations() : Promise.resolve()
        }

        getQueryCache() {
            return W(this, Ie)
        }

        getMutationCache() {
            return W(this, Pr)
        }

        getDefaultOptions() {
            return W(this, Tr)
        }

        setDefaultOptions(e) {
            ae(this, Tr, e)
        }

        setQueryDefaults(e, t) {
            W(this, _s).set(ia(e), {queryKey: e, defaultOptions: t})
        }

        getQueryDefaults(e) {
            const t = [...W(this, _s).values()], n = {};
            return t.forEach(r => {
                aa(e, r.queryKey) && Object.assign(n, r.defaultOptions)
            }), n
        }

        setMutationDefaults(e, t) {
            W(this, js).set(ia(e), {mutationKey: e, defaultOptions: t})
        }

        getMutationDefaults(e) {
            const t = [...W(this, js).values()], n = {};
            return t.forEach(r => {
                aa(e, r.mutationKey) && Object.assign(n, r.defaultOptions)
            }), n
        }

        defaultQueryOptions(e) {
            if (e._defaulted) return e;
            const t = {...W(this, Tr).queries, ...this.getQueryDefaults(e.queryKey), ...e, _defaulted: !0};
            return t.queryHash || (t.queryHash = nm(t.queryKey, t)), t.refetchOnReconnect === void 0 && (t.refetchOnReconnect = t.networkMode !== "always"), t.throwOnError === void 0 && (t.throwOnError = !!t.suspense), !t.networkMode && t.persister && (t.networkMode = "offlineFirst"), t.queryFn === rm && (t.enabled = !1), t
        }

        defaultMutationOptions(e) {
            return e != null && e._defaulted ? e : {
                ...W(this, Tr).mutations, ...(e == null ? void 0 : e.mutationKey) && this.getMutationDefaults(e.mutationKey), ...e,
                _defaulted: !0
            }
        }

        clear() {
            W(this, Ie).clear(), W(this, Pr).clear()
        }
    }, Ie = new WeakMap, Pr = new WeakMap, Tr = new WeakMap, _s = new WeakMap, js = new WeakMap, Mr = new WeakMap, Ps = new WeakMap, Ts = new WeakMap, iy),
    fR = g.createContext(void 0), pR = ({client: e, children: t}) => (g.useEffect(() => (e.mount(), () => {
        e.unmount()
    }), [e]), c.jsx(fR.Provider, {value: e, children: t}));

function ze(e) {
    if (typeof e == "string" || typeof e == "number") return "" + e;
    let t = "";
    if (Array.isArray(e)) for (let n = 0, r; n < e.length; n++) (r = ze(e[n])) !== "" && (t += (t && " ") + r); else for (let n in e) e[n] && (t += (t && " ") + n);
    return t
}

var hR = {
    value: () => {
    }
};

function ku() {
    for (var e = 0, t = arguments.length, n = {}, r; e < t; ++e) {
        if (!(r = arguments[e] + "") || r in n || /[\s.]/.test(r)) throw new Error("illegal type: " + r);
        n[r] = []
    }
    return new Zl(n)
}

function Zl(e) {
    this._ = e
}

function mR(e, t) {
    return e.trim().split(/^|\s+/).map(function (n) {
        var r = "", o = n.indexOf(".");
        if (o >= 0 && (r = n.slice(o + 1), n = n.slice(0, o)), n && !t.hasOwnProperty(n)) throw new Error("unknown type: " + n);
        return {type: n, name: r}
    })
}

Zl.prototype = ku.prototype = {
    constructor: Zl, on: function (e, t) {
        var n = this._, r = mR(e + "", n), o, s = -1, i = r.length;
        if (arguments.length < 2) {
            for (; ++s < i;) if ((o = (e = r[s]).type) && (o = gR(n[o], e.name))) return o;
            return
        }
        if (t != null && typeof t != "function") throw new Error("invalid callback: " + t);
        for (; ++s < i;) if (o = (e = r[s]).type) n[o] = Av(n[o], e.name, t); else if (t == null) for (o in n) n[o] = Av(n[o], e.name, null);
        return this
    }, copy: function () {
        var e = {}, t = this._;
        for (var n in t) e[n] = t[n].slice();
        return new Zl(e)
    }, call: function (e, t) {
        if ((o = arguments.length - 2) > 0) for (var n = new Array(o), r = 0, o, s; r < o; ++r) n[r] = arguments[r + 2];
        if (!this._.hasOwnProperty(e)) throw new Error("unknown type: " + e);
        for (s = this._[e], r = 0, o = s.length; r < o; ++r) s[r].value.apply(t, n)
    }, apply: function (e, t, n) {
        if (!this._.hasOwnProperty(e)) throw new Error("unknown type: " + e);
        for (var r = this._[e], o = 0, s = r.length; o < s; ++o) r[o].value.apply(t, n)
    }
};

function gR(e, t) {
    for (var n = 0, r = e.length, o; n < r; ++n) if ((o = e[n]).name === t) return o.value
}

function Av(e, t, n) {
    for (var r = 0, o = e.length; r < o; ++r) if (e[r].name === t) {
        e[r] = hR, e = e.slice(0, r).concat(e.slice(r + 1));
        break
    }
    return n != null && e.push({name: t, value: n}), e
}

var pp = "http://www.w3.org/1999/xhtml";
const Iv = {
    svg: "http://www.w3.org/2000/svg",
    xhtml: pp,
    xlink: "http://www.w3.org/1999/xlink",
    xml: "http://www.w3.org/XML/1998/namespace",
    xmlns: "http://www.w3.org/2000/xmlns/"
};

function _u(e) {
    var t = e += "", n = t.indexOf(":");
    return n >= 0 && (t = e.slice(0, n)) !== "xmlns" && (e = e.slice(n + 1)), Iv.hasOwnProperty(t) ? {
        space: Iv[t],
        local: e
    } : e
}

function vR(e) {
    return function () {
        var t = this.ownerDocument, n = this.namespaceURI;
        return n === pp && t.documentElement.namespaceURI === pp ? t.createElement(e) : t.createElementNS(n, e)
    }
}

function yR(e) {
    return function () {
        return this.ownerDocument.createElementNS(e.space, e.local)
    }
}

function Z1(e) {
    var t = _u(e);
    return (t.local ? yR : vR)(t)
}

function xR() {
}

function om(e) {
    return e == null ? xR : function () {
        return this.querySelector(e)
    }
}

function wR(e) {
    typeof e != "function" && (e = om(e));
    for (var t = this._groups, n = t.length, r = new Array(n), o = 0; o < n; ++o) for (var s = t[o], i = s.length, a = r[o] = new Array(i), l, u, d = 0; d < i; ++d) (l = s[d]) && (u = e.call(l, l.__data__, d, s)) && ("__data__" in l && (u.__data__ = l.__data__), a[d] = u);
    return new At(r, this._parents)
}

function bR(e) {
    return e == null ? [] : Array.isArray(e) ? e : Array.from(e)
}

function CR() {
    return []
}

function J1(e) {
    return e == null ? CR : function () {
        return this.querySelectorAll(e)
    }
}

function SR(e) {
    return function () {
        return bR(e.apply(this, arguments))
    }
}

function NR(e) {
    typeof e == "function" ? e = SR(e) : e = J1(e);
    for (var t = this._groups, n = t.length, r = [], o = [], s = 0; s < n; ++s) for (var i = t[s], a = i.length, l, u = 0; u < a; ++u) (l = i[u]) && (r.push(e.call(l, l.__data__, u, i)), o.push(l));
    return new At(r, o)
}

function eb(e) {
    return function () {
        return this.matches(e)
    }
}

function tb(e) {
    return function (t) {
        return t.matches(e)
    }
}

var ER = Array.prototype.find;

function kR(e) {
    return function () {
        return ER.call(this.children, e)
    }
}

function _R() {
    return this.firstElementChild
}

function jR(e) {
    return this.select(e == null ? _R : kR(typeof e == "function" ? e : tb(e)))
}

var PR = Array.prototype.filter;

function TR() {
    return Array.from(this.children)
}

function MR(e) {
    return function () {
        return PR.call(this.children, e)
    }
}

function RR(e) {
    return this.selectAll(e == null ? TR : MR(typeof e == "function" ? e : tb(e)))
}

function AR(e) {
    typeof e != "function" && (e = eb(e));
    for (var t = this._groups, n = t.length, r = new Array(n), o = 0; o < n; ++o) for (var s = t[o], i = s.length, a = r[o] = [], l, u = 0; u < i; ++u) (l = s[u]) && e.call(l, l.__data__, u, s) && a.push(l);
    return new At(r, this._parents)
}

function nb(e) {
    return new Array(e.length)
}

function IR() {
    return new At(this._enter || this._groups.map(nb), this._parents)
}

function Ic(e, t) {
    this.ownerDocument = e.ownerDocument, this.namespaceURI = e.namespaceURI, this._next = null, this._parent = e, this.__data__ = t
}

Ic.prototype = {
    constructor: Ic, appendChild: function (e) {
        return this._parent.insertBefore(e, this._next)
    }, insertBefore: function (e, t) {
        return this._parent.insertBefore(e, t)
    }, querySelector: function (e) {
        return this._parent.querySelector(e)
    }, querySelectorAll: function (e) {
        return this._parent.querySelectorAll(e)
    }
};

function OR(e) {
    return function () {
        return e
    }
}

function DR(e, t, n, r, o, s) {
    for (var i = 0, a, l = t.length, u = s.length; i < u; ++i) (a = t[i]) ? (a.__data__ = s[i], r[i] = a) : n[i] = new Ic(e, s[i]);
    for (; i < l; ++i) (a = t[i]) && (o[i] = a)
}

function $R(e, t, n, r, o, s, i) {
    var a, l, u = new Map, d = t.length, f = s.length, p = new Array(d), h;
    for (a = 0; a < d; ++a) (l = t[a]) && (p[a] = h = i.call(l, l.__data__, a, t) + "", u.has(h) ? o[a] = l : u.set(h, l));
    for (a = 0; a < f; ++a) h = i.call(e, s[a], a, s) + "", (l = u.get(h)) ? (r[a] = l, l.__data__ = s[a], u.delete(h)) : n[a] = new Ic(e, s[a]);
    for (a = 0; a < d; ++a) (l = t[a]) && u.get(p[a]) === l && (o[a] = l)
}

function LR(e) {
    return e.__data__
}

function FR(e, t) {
    if (!arguments.length) return Array.from(this, LR);
    var n = t ? $R : DR, r = this._parents, o = this._groups;
    typeof e != "function" && (e = OR(e));
    for (var s = o.length, i = new Array(s), a = new Array(s), l = new Array(s), u = 0; u < s; ++u) {
        var d = r[u], f = o[u], p = f.length, h = zR(e.call(d, d && d.__data__, u, r)), x = h.length,
            m = a[u] = new Array(x), b = i[u] = new Array(x), y = l[u] = new Array(p);
        n(d, f, m, b, y, h, t);
        for (var w = 0, v = 0, C, S; w < x; ++w) if (C = m[w]) {
            for (w >= v && (v = w + 1); !(S = b[v]) && ++v < x;) ;
            C._next = S || null
        }
    }
    return i = new At(i, r), i._enter = a, i._exit = l, i
}

function zR(e) {
    return typeof e == "object" && "length" in e ? e : Array.from(e)
}

function BR() {
    return new At(this._exit || this._groups.map(nb), this._parents)
}

function VR(e, t, n) {
    var r = this.enter(), o = this, s = this.exit();
    return typeof e == "function" ? (r = e(r), r && (r = r.selection())) : r = r.append(e + ""), t != null && (o = t(o), o && (o = o.selection())), n == null ? s.remove() : n(s), r && o ? r.merge(o).order() : o
}

function HR(e) {
    for (var t = e.selection ? e.selection() : e, n = this._groups, r = t._groups, o = n.length, s = r.length, i = Math.min(o, s), a = new Array(o), l = 0; l < i; ++l) for (var u = n[l], d = r[l], f = u.length, p = a[l] = new Array(f), h, x = 0; x < f; ++x) (h = u[x] || d[x]) && (p[x] = h);
    for (; l < o; ++l) a[l] = n[l];
    return new At(a, this._parents)
}

function UR() {
    for (var e = this._groups, t = -1, n = e.length; ++t < n;) for (var r = e[t], o = r.length - 1, s = r[o], i; --o >= 0;) (i = r[o]) && (s && i.compareDocumentPosition(s) ^ 4 && s.parentNode.insertBefore(i, s), s = i);
    return this
}

function WR(e) {
    e || (e = KR);

    function t(f, p) {
        return f && p ? e(f.__data__, p.__data__) : !f - !p
    }

    for (var n = this._groups, r = n.length, o = new Array(r), s = 0; s < r; ++s) {
        for (var i = n[s], a = i.length, l = o[s] = new Array(a), u, d = 0; d < a; ++d) (u = i[d]) && (l[d] = u);
        l.sort(t)
    }
    return new At(o, this._parents).order()
}

function KR(e, t) {
    return e < t ? -1 : e > t ? 1 : e >= t ? 0 : NaN
}

function YR() {
    var e = arguments[0];
    return arguments[0] = this, e.apply(null, arguments), this
}

function GR() {
    return Array.from(this)
}

function XR() {
    for (var e = this._groups, t = 0, n = e.length; t < n; ++t) for (var r = e[t], o = 0, s = r.length; o < s; ++o) {
        var i = r[o];
        if (i) return i
    }
    return null
}

function qR() {
    let e = 0;
    for (const t of this) ++e;
    return e
}

function QR() {
    return !this.node()
}

function ZR(e) {
    for (var t = this._groups, n = 0, r = t.length; n < r; ++n) for (var o = t[n], s = 0, i = o.length, a; s < i; ++s) (a = o[s]) && e.call(a, a.__data__, s, o);
    return this
}

function JR(e) {
    return function () {
        this.removeAttribute(e)
    }
}

function eA(e) {
    return function () {
        this.removeAttributeNS(e.space, e.local)
    }
}

function tA(e, t) {
    return function () {
        this.setAttribute(e, t)
    }
}

function nA(e, t) {
    return function () {
        this.setAttributeNS(e.space, e.local, t)
    }
}

function rA(e, t) {
    return function () {
        var n = t.apply(this, arguments);
        n == null ? this.removeAttribute(e) : this.setAttribute(e, n)
    }
}

function oA(e, t) {
    return function () {
        var n = t.apply(this, arguments);
        n == null ? this.removeAttributeNS(e.space, e.local) : this.setAttributeNS(e.space, e.local, n)
    }
}

function sA(e, t) {
    var n = _u(e);
    if (arguments.length < 2) {
        var r = this.node();
        return n.local ? r.getAttributeNS(n.space, n.local) : r.getAttribute(n)
    }
    return this.each((t == null ? n.local ? eA : JR : typeof t == "function" ? n.local ? oA : rA : n.local ? nA : tA)(n, t))
}

function rb(e) {
    return e.ownerDocument && e.ownerDocument.defaultView || e.document && e || e.defaultView
}

function iA(e) {
    return function () {
        this.style.removeProperty(e)
    }
}

function aA(e, t, n) {
    return function () {
        this.style.setProperty(e, t, n)
    }
}

function lA(e, t, n) {
    return function () {
        var r = t.apply(this, arguments);
        r == null ? this.style.removeProperty(e) : this.style.setProperty(e, r, n)
    }
}

function cA(e, t, n) {
    return arguments.length > 1 ? this.each((t == null ? iA : typeof t == "function" ? lA : aA)(e, t, n ?? "")) : zs(this.node(), e)
}

function zs(e, t) {
    return e.style.getPropertyValue(t) || rb(e).getComputedStyle(e, null).getPropertyValue(t)
}

function uA(e) {
    return function () {
        delete this[e]
    }
}

function dA(e, t) {
    return function () {
        this[e] = t
    }
}

function fA(e, t) {
    return function () {
        var n = t.apply(this, arguments);
        n == null ? delete this[e] : this[e] = n
    }
}

function pA(e, t) {
    return arguments.length > 1 ? this.each((t == null ? uA : typeof t == "function" ? fA : dA)(e, t)) : this.node()[e]
}

function ob(e) {
    return e.trim().split(/^|\s+/)
}

function sm(e) {
    return e.classList || new sb(e)
}

function sb(e) {
    this._node = e, this._names = ob(e.getAttribute("class") || "")
}

sb.prototype = {
    add: function (e) {
        var t = this._names.indexOf(e);
        t < 0 && (this._names.push(e), this._node.setAttribute("class", this._names.join(" ")))
    }, remove: function (e) {
        var t = this._names.indexOf(e);
        t >= 0 && (this._names.splice(t, 1), this._node.setAttribute("class", this._names.join(" ")))
    }, contains: function (e) {
        return this._names.indexOf(e) >= 0
    }
};

function ib(e, t) {
    for (var n = sm(e), r = -1, o = t.length; ++r < o;) n.add(t[r])
}

function ab(e, t) {
    for (var n = sm(e), r = -1, o = t.length; ++r < o;) n.remove(t[r])
}

function hA(e) {
    return function () {
        ib(this, e)
    }
}

function mA(e) {
    return function () {
        ab(this, e)
    }
}

function gA(e, t) {
    return function () {
        (t.apply(this, arguments) ? ib : ab)(this, e)
    }
}

function vA(e, t) {
    var n = ob(e + "");
    if (arguments.length < 2) {
        for (var r = sm(this.node()), o = -1, s = n.length; ++o < s;) if (!r.contains(n[o])) return !1;
        return !0
    }
    return this.each((typeof t == "function" ? gA : t ? hA : mA)(n, t))
}

function yA() {
    this.textContent = ""
}

function xA(e) {
    return function () {
        this.textContent = e
    }
}

function wA(e) {
    return function () {
        var t = e.apply(this, arguments);
        this.textContent = t ?? ""
    }
}

function bA(e) {
    return arguments.length ? this.each(e == null ? yA : (typeof e == "function" ? wA : xA)(e)) : this.node().textContent
}

function CA() {
    this.innerHTML = ""
}

function SA(e) {
    return function () {
        this.innerHTML = e
    }
}

function NA(e) {
    return function () {
        var t = e.apply(this, arguments);
        this.innerHTML = t ?? ""
    }
}

function EA(e) {
    return arguments.length ? this.each(e == null ? CA : (typeof e == "function" ? NA : SA)(e)) : this.node().innerHTML
}

function kA() {
    this.nextSibling && this.parentNode.appendChild(this)
}

function _A() {
    return this.each(kA)
}

function jA() {
    this.previousSibling && this.parentNode.insertBefore(this, this.parentNode.firstChild)
}

function PA() {
    return this.each(jA)
}

function TA(e) {
    var t = typeof e == "function" ? e : Z1(e);
    return this.select(function () {
        return this.appendChild(t.apply(this, arguments))
    })
}

function MA() {
    return null
}

function RA(e, t) {
    var n = typeof e == "function" ? e : Z1(e), r = t == null ? MA : typeof t == "function" ? t : om(t);
    return this.select(function () {
        return this.insertBefore(n.apply(this, arguments), r.apply(this, arguments) || null)
    })
}

function AA() {
    var e = this.parentNode;
    e && e.removeChild(this)
}

function IA() {
    return this.each(AA)
}

function OA() {
    var e = this.cloneNode(!1), t = this.parentNode;
    return t ? t.insertBefore(e, this.nextSibling) : e
}

function DA() {
    var e = this.cloneNode(!0), t = this.parentNode;
    return t ? t.insertBefore(e, this.nextSibling) : e
}

function $A(e) {
    return this.select(e ? DA : OA)
}

function LA(e) {
    return arguments.length ? this.property("__data__", e) : this.node().__data__
}

function FA(e) {
    return function (t) {
        e.call(this, t, this.__data__)
    }
}

function zA(e) {
    return e.trim().split(/^|\s+/).map(function (t) {
        var n = "", r = t.indexOf(".");
        return r >= 0 && (n = t.slice(r + 1), t = t.slice(0, r)), {type: t, name: n}
    })
}

function BA(e) {
    return function () {
        var t = this.__on;
        if (t) {
            for (var n = 0, r = -1, o = t.length, s; n < o; ++n) s = t[n], (!e.type || s.type === e.type) && s.name === e.name ? this.removeEventListener(s.type, s.listener, s.options) : t[++r] = s;
            ++r ? t.length = r : delete this.__on
        }
    }
}

function VA(e, t, n) {
    return function () {
        var r = this.__on, o, s = FA(t);
        if (r) {
            for (var i = 0, a = r.length; i < a; ++i) if ((o = r[i]).type === e.type && o.name === e.name) {
                this.removeEventListener(o.type, o.listener, o.options), this.addEventListener(o.type, o.listener = s, o.options = n), o.value = t;
                return
            }
        }
        this.addEventListener(e.type, s, n), o = {
            type: e.type,
            name: e.name,
            value: t,
            listener: s,
            options: n
        }, r ? r.push(o) : this.__on = [o]
    }
}

function HA(e, t, n) {
    var r = zA(e + ""), o, s = r.length, i;
    if (arguments.length < 2) {
        var a = this.node().__on;
        if (a) {
            for (var l = 0, u = a.length, d; l < u; ++l) for (o = 0, d = a[l]; o < s; ++o) if ((i = r[o]).type === d.type && i.name === d.name) return d.value
        }
        return
    }
    for (a = t ? VA : BA, o = 0; o < s; ++o) this.each(a(r[o], t, n));
    return this
}

function lb(e, t, n) {
    var r = rb(e), o = r.CustomEvent;
    typeof o == "function" ? o = new o(t, n) : (o = r.document.createEvent("Event"), n ? (o.initEvent(t, n.bubbles, n.cancelable), o.detail = n.detail) : o.initEvent(t, !1, !1)), e.dispatchEvent(o)
}

function UA(e, t) {
    return function () {
        return lb(this, e, t)
    }
}

function WA(e, t) {
    return function () {
        return lb(this, e, t.apply(this, arguments))
    }
}

function KA(e, t) {
    return this.each((typeof t == "function" ? WA : UA)(e, t))
}

function* YA() {
    for (var e = this._groups, t = 0, n = e.length; t < n; ++t) for (var r = e[t], o = 0, s = r.length, i; o < s; ++o) (i = r[o]) && (yield i)
}

var cb = [null];

function At(e, t) {
    this._groups = e, this._parents = t
}

function Ra() {
    return new At([[document.documentElement]], cb)
}

function GA() {
    return this
}

At.prototype = Ra.prototype = {
    constructor: At,
    select: wR,
    selectAll: NR,
    selectChild: jR,
    selectChildren: RR,
    filter: AR,
    data: FR,
    enter: IR,
    exit: BR,
    join: VR,
    merge: HR,
    selection: GA,
    order: UR,
    sort: WR,
    call: YR,
    nodes: GR,
    node: XR,
    size: qR,
    empty: QR,
    each: ZR,
    attr: sA,
    style: cA,
    property: pA,
    classed: vA,
    text: bA,
    html: EA,
    raise: _A,
    lower: PA,
    append: TA,
    insert: RA,
    remove: IA,
    clone: $A,
    datum: LA,
    on: HA,
    dispatch: KA,
    [Symbol.iterator]: YA
};

function jt(e) {
    return typeof e == "string" ? new At([[document.querySelector(e)]], [document.documentElement]) : new At([[e]], cb)
}

function XA(e) {
    let t;
    for (; t = e.sourceEvent;) e = t;
    return e
}

function rn(e, t) {
    if (e = XA(e), t === void 0 && (t = e.currentTarget), t) {
        var n = t.ownerSVGElement || t;
        if (n.createSVGPoint) {
            var r = n.createSVGPoint();
            return r.x = e.clientX, r.y = e.clientY, r = r.matrixTransform(t.getScreenCTM().inverse()), [r.x, r.y]
        }
        if (t.getBoundingClientRect) {
            var o = t.getBoundingClientRect();
            return [e.clientX - o.left - t.clientLeft, e.clientY - o.top - t.clientTop]
        }
    }
    return [e.pageX, e.pageY]
}

const qA = {passive: !1}, la = {capture: !0, passive: !1};

function Bd(e) {
    e.stopImmediatePropagation()
}

function xs(e) {
    e.preventDefault(), e.stopImmediatePropagation()
}

function ub(e) {
    var t = e.document.documentElement, n = jt(e).on("dragstart.drag", xs, la);
    "onselectstart" in t ? n.on("selectstart.drag", xs, la) : (t.__noselect = t.style.MozUserSelect, t.style.MozUserSelect = "none")
}

function db(e, t) {
    var n = e.document.documentElement, r = jt(e).on("dragstart.drag", null);
    t && (r.on("click.drag", xs, la), setTimeout(function () {
        r.on("click.drag", null)
    }, 0)), "onselectstart" in n ? r.on("selectstart.drag", null) : (n.style.MozUserSelect = n.__noselect, delete n.__noselect)
}

const Sl = e => () => e;

function hp(e, {
    sourceEvent: t,
    subject: n,
    target: r,
    identifier: o,
    active: s,
    x: i,
    y: a,
    dx: l,
    dy: u,
    dispatch: d
}) {
    Object.defineProperties(this, {
        type: {value: e, enumerable: !0, configurable: !0},
        sourceEvent: {value: t, enumerable: !0, configurable: !0},
        subject: {value: n, enumerable: !0, configurable: !0},
        target: {value: r, enumerable: !0, configurable: !0},
        identifier: {value: o, enumerable: !0, configurable: !0},
        active: {value: s, enumerable: !0, configurable: !0},
        x: {value: i, enumerable: !0, configurable: !0},
        y: {value: a, enumerable: !0, configurable: !0},
        dx: {value: l, enumerable: !0, configurable: !0},
        dy: {value: u, enumerable: !0, configurable: !0},
        _: {value: d}
    })
}

hp.prototype.on = function () {
    var e = this._.on.apply(this._, arguments);
    return e === this._ ? this : e
};

function QA(e) {
    return !e.ctrlKey && !e.button
}

function ZA() {
    return this.parentNode
}

function JA(e, t) {
    return t ?? {x: e.x, y: e.y}
}

function eI() {
    return navigator.maxTouchPoints || "ontouchstart" in this
}

function fb() {
    var e = QA, t = ZA, n = JA, r = eI, o = {}, s = ku("start", "drag", "end"), i = 0, a, l, u, d, f = 0;

    function p(C) {
        C.on("mousedown.drag", h).filter(r).on("touchstart.drag", b).on("touchmove.drag", y, qA).on("touchend.drag touchcancel.drag", w).style("touch-action", "none").style("-webkit-tap-highlight-color", "rgba(0,0,0,0)")
    }

    function h(C, S) {
        if (!(d || !e.call(this, C, S))) {
            var N = v(this, t.call(this, C, S), C, S, "mouse");
            N && (jt(C.view).on("mousemove.drag", x, la).on("mouseup.drag", m, la), ub(C.view), Bd(C), u = !1, a = C.clientX, l = C.clientY, N("start", C))
        }
    }

    function x(C) {
        if (xs(C), !u) {
            var S = C.clientX - a, N = C.clientY - l;
            u = S * S + N * N > f
        }
        o.mouse("drag", C)
    }

    function m(C) {
        jt(C.view).on("mousemove.drag mouseup.drag", null), db(C.view, u), xs(C), o.mouse("end", C)
    }

    function b(C, S) {
        if (e.call(this, C, S)) {
            var N = C.changedTouches, E = t.call(this, C, S), T = N.length, O, D;
            for (O = 0; O < T; ++O) (D = v(this, E, C, S, N[O].identifier, N[O])) && (Bd(C), D("start", C, N[O]))
        }
    }

    function y(C) {
        var S = C.changedTouches, N = S.length, E, T;
        for (E = 0; E < N; ++E) (T = o[S[E].identifier]) && (xs(C), T("drag", C, S[E]))
    }

    function w(C) {
        var S = C.changedTouches, N = S.length, E, T;
        for (d && clearTimeout(d), d = setTimeout(function () {
            d = null
        }, 500), E = 0; E < N; ++E) (T = o[S[E].identifier]) && (Bd(C), T("end", C, S[E]))
    }

    function v(C, S, N, E, T, O) {
        var D = s.copy(), L = rn(O || N, S), $, B, k;
        if ((k = n.call(C, new hp("beforestart", {
            sourceEvent: N,
            target: p,
            identifier: T,
            active: i,
            x: L[0],
            y: L[1],
            dx: 0,
            dy: 0,
            dispatch: D
        }), E)) != null) return $ = k.x - L[0] || 0, B = k.y - L[1] || 0, function M(j, A, P) {
            var _ = L, R;
            switch (j) {
                case"start":
                    o[T] = M, R = i++;
                    break;
                case"end":
                    delete o[T], --i;
                case"drag":
                    L = rn(P || A, S), R = i;
                    break
            }
            D.call(j, C, new hp(j, {
                sourceEvent: A,
                subject: k,
                target: p,
                identifier: T,
                active: R,
                x: L[0] + $,
                y: L[1] + B,
                dx: L[0] - _[0],
                dy: L[1] - _[1],
                dispatch: D
            }), E)
        }
    }

    return p.filter = function (C) {
        return arguments.length ? (e = typeof C == "function" ? C : Sl(!!C), p) : e
    }, p.container = function (C) {
        return arguments.length ? (t = typeof C == "function" ? C : Sl(C), p) : t
    }, p.subject = function (C) {
        return arguments.length ? (n = typeof C == "function" ? C : Sl(C), p) : n
    }, p.touchable = function (C) {
        return arguments.length ? (r = typeof C == "function" ? C : Sl(!!C), p) : r
    }, p.on = function () {
        var C = s.on.apply(s, arguments);
        return C === s ? p : C
    }, p.clickDistance = function (C) {
        return arguments.length ? (f = (C = +C) * C, p) : Math.sqrt(f)
    }, p
}

function im(e, t, n) {
    e.prototype = t.prototype = n, n.constructor = e
}

function pb(e, t) {
    var n = Object.create(e.prototype);
    for (var r in t) n[r] = t[r];
    return n
}

function Aa() {
}

var ca = .7, Oc = 1 / ca, ws = "\\s*([+-]?\\d+)\\s*", ua = "\\s*([+-]?(?:\\d*\\.)?\\d+(?:[eE][+-]?\\d+)?)\\s*",
    Rn = "\\s*([+-]?(?:\\d*\\.)?\\d+(?:[eE][+-]?\\d+)?)%\\s*", tI = /^#([0-9a-f]{3,8})$/,
    nI = new RegExp(`^rgb\\(${ws},${ws},${ws}\\)$`), rI = new RegExp(`^rgb\\(${Rn},${Rn},${Rn}\\)$`),
    oI = new RegExp(`^rgba\\(${ws},${ws},${ws},${ua}\\)$`), sI = new RegExp(`^rgba\\(${Rn},${Rn},${Rn},${ua}\\)$`),
    iI = new RegExp(`^hsl\\(${ua},${Rn},${Rn}\\)$`), aI = new RegExp(`^hsla\\(${ua},${Rn},${Rn},${ua}\\)$`), Ov = {
        aliceblue: 15792383,
        antiquewhite: 16444375,
        aqua: 65535,
        aquamarine: 8388564,
        azure: 15794175,
        beige: 16119260,
        bisque: 16770244,
        black: 0,
        blanchedalmond: 16772045,
        blue: 255,
        blueviolet: 9055202,
        brown: 10824234,
        burlywood: 14596231,
        cadetblue: 6266528,
        chartreuse: 8388352,
        chocolate: 13789470,
        coral: 16744272,
        cornflowerblue: 6591981,
        cornsilk: 16775388,
        crimson: 14423100,
        cyan: 65535,
        darkblue: 139,
        darkcyan: 35723,
        darkgoldenrod: 12092939,
        darkgray: 11119017,
        darkgreen: 25600,
        darkgrey: 11119017,
        darkkhaki: 12433259,
        darkmagenta: 9109643,
        darkolivegreen: 5597999,
        darkorange: 16747520,
        darkorchid: 10040012,
        darkred: 9109504,
        darksalmon: 15308410,
        darkseagreen: 9419919,
        darkslateblue: 4734347,
        darkslategray: 3100495,
        darkslategrey: 3100495,
        darkturquoise: 52945,
        darkviolet: 9699539,
        deeppink: 16716947,
        deepskyblue: 49151,
        dimgray: 6908265,
        dimgrey: 6908265,
        dodgerblue: 2003199,
        firebrick: 11674146,
        floralwhite: 16775920,
        forestgreen: 2263842,
        fuchsia: 16711935,
        gainsboro: 14474460,
        ghostwhite: 16316671,
        gold: 16766720,
        goldenrod: 14329120,
        gray: 8421504,
        green: 32768,
        greenyellow: 11403055,
        grey: 8421504,
        honeydew: 15794160,
        hotpink: 16738740,
        indianred: 13458524,
        indigo: 4915330,
        ivory: 16777200,
        khaki: 15787660,
        lavender: 15132410,
        lavenderblush: 16773365,
        lawngreen: 8190976,
        lemonchiffon: 16775885,
        lightblue: 11393254,
        lightcoral: 15761536,
        lightcyan: 14745599,
        lightgoldenrodyellow: 16448210,
        lightgray: 13882323,
        lightgreen: 9498256,
        lightgrey: 13882323,
        lightpink: 16758465,
        lightsalmon: 16752762,
        lightseagreen: 2142890,
        lightskyblue: 8900346,
        lightslategray: 7833753,
        lightslategrey: 7833753,
        lightsteelblue: 11584734,
        lightyellow: 16777184,
        lime: 65280,
        limegreen: 3329330,
        linen: 16445670,
        magenta: 16711935,
        maroon: 8388608,
        mediumaquamarine: 6737322,
        mediumblue: 205,
        mediumorchid: 12211667,
        mediumpurple: 9662683,
        mediumseagreen: 3978097,
        mediumslateblue: 8087790,
        mediumspringgreen: 64154,
        mediumturquoise: 4772300,
        mediumvioletred: 13047173,
        midnightblue: 1644912,
        mintcream: 16121850,
        mistyrose: 16770273,
        moccasin: 16770229,
        navajowhite: 16768685,
        navy: 128,
        oldlace: 16643558,
        olive: 8421376,
        olivedrab: 7048739,
        orange: 16753920,
        orangered: 16729344,
        orchid: 14315734,
        palegoldenrod: 15657130,
        palegreen: 10025880,
        paleturquoise: 11529966,
        palevioletred: 14381203,
        papayawhip: 16773077,
        peachpuff: 16767673,
        peru: 13468991,
        pink: 16761035,
        plum: 14524637,
        powderblue: 11591910,
        purple: 8388736,
        rebeccapurple: 6697881,
        red: 16711680,
        rosybrown: 12357519,
        royalblue: 4286945,
        saddlebrown: 9127187,
        salmon: 16416882,
        sandybrown: 16032864,
        seagreen: 3050327,
        seashell: 16774638,
        sienna: 10506797,
        silver: 12632256,
        skyblue: 8900331,
        slateblue: 6970061,
        slategray: 7372944,
        slategrey: 7372944,
        snow: 16775930,
        springgreen: 65407,
        steelblue: 4620980,
        tan: 13808780,
        teal: 32896,
        thistle: 14204888,
        tomato: 16737095,
        turquoise: 4251856,
        violet: 15631086,
        wheat: 16113331,
        white: 16777215,
        whitesmoke: 16119285,
        yellow: 16776960,
        yellowgreen: 10145074
    };
im(Aa, da, {
    copy(e) {
        return Object.assign(new this.constructor, this, e)
    }, displayable() {
        return this.rgb().displayable()
    }, hex: Dv, formatHex: Dv, formatHex8: lI, formatHsl: cI, formatRgb: $v, toString: $v
});

function Dv() {
    return this.rgb().formatHex()
}

function lI() {
    return this.rgb().formatHex8()
}

function cI() {
    return hb(this).formatHsl()
}

function $v() {
    return this.rgb().formatRgb()
}

function da(e) {
    var t, n;
    return e = (e + "").trim().toLowerCase(), (t = tI.exec(e)) ? (n = t[1].length, t = parseInt(t[1], 16), n === 6 ? Lv(t) : n === 3 ? new vt(t >> 8 & 15 | t >> 4 & 240, t >> 4 & 15 | t & 240, (t & 15) << 4 | t & 15, 1) : n === 8 ? Nl(t >> 24 & 255, t >> 16 & 255, t >> 8 & 255, (t & 255) / 255) : n === 4 ? Nl(t >> 12 & 15 | t >> 8 & 240, t >> 8 & 15 | t >> 4 & 240, t >> 4 & 15 | t & 240, ((t & 15) << 4 | t & 15) / 255) : null) : (t = nI.exec(e)) ? new vt(t[1], t[2], t[3], 1) : (t = rI.exec(e)) ? new vt(t[1] * 255 / 100, t[2] * 255 / 100, t[3] * 255 / 100, 1) : (t = oI.exec(e)) ? Nl(t[1], t[2], t[3], t[4]) : (t = sI.exec(e)) ? Nl(t[1] * 255 / 100, t[2] * 255 / 100, t[3] * 255 / 100, t[4]) : (t = iI.exec(e)) ? Bv(t[1], t[2] / 100, t[3] / 100, 1) : (t = aI.exec(e)) ? Bv(t[1], t[2] / 100, t[3] / 100, t[4]) : Ov.hasOwnProperty(e) ? Lv(Ov[e]) : e === "transparent" ? new vt(NaN, NaN, NaN, 0) : null
}

function Lv(e) {
    return new vt(e >> 16 & 255, e >> 8 & 255, e & 255, 1)
}

function Nl(e, t, n, r) {
    return r <= 0 && (e = t = n = NaN), new vt(e, t, n, r)
}

function uI(e) {
    return e instanceof Aa || (e = da(e)), e ? (e = e.rgb(), new vt(e.r, e.g, e.b, e.opacity)) : new vt
}

function mp(e, t, n, r) {
    return arguments.length === 1 ? uI(e) : new vt(e, t, n, r ?? 1)
}

function vt(e, t, n, r) {
    this.r = +e, this.g = +t, this.b = +n, this.opacity = +r
}

im(vt, mp, pb(Aa, {
    brighter(e) {
        return e = e == null ? Oc : Math.pow(Oc, e), new vt(this.r * e, this.g * e, this.b * e, this.opacity)
    }, darker(e) {
        return e = e == null ? ca : Math.pow(ca, e), new vt(this.r * e, this.g * e, this.b * e, this.opacity)
    }, rgb() {
        return this
    }, clamp() {
        return new vt(So(this.r), So(this.g), So(this.b), Dc(this.opacity))
    }, displayable() {
        return -.5 <= this.r && this.r < 255.5 && -.5 <= this.g && this.g < 255.5 && -.5 <= this.b && this.b < 255.5 && 0 <= this.opacity && this.opacity <= 1
    }, hex: Fv, formatHex: Fv, formatHex8: dI, formatRgb: zv, toString: zv
}));

function Fv() {
    return `#${po(this.r)}${po(this.g)}${po(this.b)}`
}

function dI() {
    return `#${po(this.r)}${po(this.g)}${po(this.b)}${po((isNaN(this.opacity) ? 1 : this.opacity) * 255)}`
}

function zv() {
    const e = Dc(this.opacity);
    return `${e === 1 ? "rgb(" : "rgba("}${So(this.r)}, ${So(this.g)}, ${So(this.b)}${e === 1 ? ")" : `, ${e})`}`
}

function Dc(e) {
    return isNaN(e) ? 1 : Math.max(0, Math.min(1, e))
}

function So(e) {
    return Math.max(0, Math.min(255, Math.round(e) || 0))
}

function po(e) {
    return e = So(e), (e < 16 ? "0" : "") + e.toString(16)
}

function Bv(e, t, n, r) {
    return r <= 0 ? e = t = n = NaN : n <= 0 || n >= 1 ? e = t = NaN : t <= 0 && (e = NaN), new sn(e, t, n, r)
}

function hb(e) {
    if (e instanceof sn) return new sn(e.h, e.s, e.l, e.opacity);
    if (e instanceof Aa || (e = da(e)), !e) return new sn;
    if (e instanceof sn) return e;
    e = e.rgb();
    var t = e.r / 255, n = e.g / 255, r = e.b / 255, o = Math.min(t, n, r), s = Math.max(t, n, r), i = NaN, a = s - o,
        l = (s + o) / 2;
    return a ? (t === s ? i = (n - r) / a + (n < r) * 6 : n === s ? i = (r - t) / a + 2 : i = (t - n) / a + 4, a /= l < .5 ? s + o : 2 - s - o, i *= 60) : a = l > 0 && l < 1 ? 0 : i, new sn(i, a, l, e.opacity)
}

function fI(e, t, n, r) {
    return arguments.length === 1 ? hb(e) : new sn(e, t, n, r ?? 1)
}

function sn(e, t, n, r) {
    this.h = +e, this.s = +t, this.l = +n, this.opacity = +r
}

im(sn, fI, pb(Aa, {
    brighter(e) {
        return e = e == null ? Oc : Math.pow(Oc, e), new sn(this.h, this.s, this.l * e, this.opacity)
    }, darker(e) {
        return e = e == null ? ca : Math.pow(ca, e), new sn(this.h, this.s, this.l * e, this.opacity)
    }, rgb() {
        var e = this.h % 360 + (this.h < 0) * 360, t = isNaN(e) || isNaN(this.s) ? 0 : this.s, n = this.l,
            r = n + (n < .5 ? n : 1 - n) * t, o = 2 * n - r;
        return new vt(Vd(e >= 240 ? e - 240 : e + 120, o, r), Vd(e, o, r), Vd(e < 120 ? e + 240 : e - 120, o, r), this.opacity)
    }, clamp() {
        return new sn(Vv(this.h), El(this.s), El(this.l), Dc(this.opacity))
    }, displayable() {
        return (0 <= this.s && this.s <= 1 || isNaN(this.s)) && 0 <= this.l && this.l <= 1 && 0 <= this.opacity && this.opacity <= 1
    }, formatHsl() {
        const e = Dc(this.opacity);
        return `${e === 1 ? "hsl(" : "hsla("}${Vv(this.h)}, ${El(this.s) * 100}%, ${El(this.l) * 100}%${e === 1 ? ")" : `, ${e})`}`
    }
}));

function Vv(e) {
    return e = (e || 0) % 360, e < 0 ? e + 360 : e
}

function El(e) {
    return Math.max(0, Math.min(1, e || 0))
}

function Vd(e, t, n) {
    return (e < 60 ? t + (n - t) * e / 60 : e < 180 ? n : e < 240 ? t + (n - t) * (240 - e) / 60 : t) * 255
}

const mb = e => () => e;

function pI(e, t) {
    return function (n) {
        return e + n * t
    }
}

function hI(e, t, n) {
    return e = Math.pow(e, n), t = Math.pow(t, n) - e, n = 1 / n, function (r) {
        return Math.pow(e + r * t, n)
    }
}

function mI(e) {
    return (e = +e) == 1 ? gb : function (t, n) {
        return n - t ? hI(t, n, e) : mb(isNaN(t) ? n : t)
    }
}

function gb(e, t) {
    var n = t - e;
    return n ? pI(e, n) : mb(isNaN(e) ? t : e)
}

const Hv = function e(t) {
    var n = mI(t);

    function r(o, s) {
        var i = n((o = mp(o)).r, (s = mp(s)).r), a = n(o.g, s.g), l = n(o.b, s.b), u = gb(o.opacity, s.opacity);
        return function (d) {
            return o.r = i(d), o.g = a(d), o.b = l(d), o.opacity = u(d), o + ""
        }
    }

    return r.gamma = e, r
}(1);

function Sr(e, t) {
    return e = +e, t = +t, function (n) {
        return e * (1 - n) + t * n
    }
}

var gp = /[-+]?(?:\d+\.?\d*|\.?\d+)(?:[eE][-+]?\d+)?/g, Hd = new RegExp(gp.source, "g");

function gI(e) {
    return function () {
        return e
    }
}

function vI(e) {
    return function (t) {
        return e(t) + ""
    }
}

function yI(e, t) {
    var n = gp.lastIndex = Hd.lastIndex = 0, r, o, s, i = -1, a = [], l = [];
    for (e = e + "", t = t + ""; (r = gp.exec(e)) && (o = Hd.exec(t));) (s = o.index) > n && (s = t.slice(n, s), a[i] ? a[i] += s : a[++i] = s), (r = r[0]) === (o = o[0]) ? a[i] ? a[i] += o : a[++i] = o : (a[++i] = null, l.push({
        i,
        x: Sr(r, o)
    })), n = Hd.lastIndex;
    return n < t.length && (s = t.slice(n), a[i] ? a[i] += s : a[++i] = s), a.length < 2 ? l[0] ? vI(l[0].x) : gI(t) : (t = l.length, function (u) {
        for (var d = 0, f; d < t; ++d) a[(f = l[d]).i] = f.x(u);
        return a.join("")
    })
}

var Uv = 180 / Math.PI, vp = {translateX: 0, translateY: 0, rotate: 0, skewX: 0, scaleX: 1, scaleY: 1};

function vb(e, t, n, r, o, s) {
    var i, a, l;
    return (i = Math.sqrt(e * e + t * t)) && (e /= i, t /= i), (l = e * n + t * r) && (n -= e * l, r -= t * l), (a = Math.sqrt(n * n + r * r)) && (n /= a, r /= a, l /= a), e * r < t * n && (e = -e, t = -t, l = -l, i = -i), {
        translateX: o,
        translateY: s,
        rotate: Math.atan2(t, e) * Uv,
        skewX: Math.atan(l) * Uv,
        scaleX: i,
        scaleY: a
    }
}

var kl;

function xI(e) {
    const t = new (typeof DOMMatrix == "function" ? DOMMatrix : WebKitCSSMatrix)(e + "");
    return t.isIdentity ? vp : vb(t.a, t.b, t.c, t.d, t.e, t.f)
}

function wI(e) {
    return e == null || (kl || (kl = document.createElementNS("http://www.w3.org/2000/svg", "g")), kl.setAttribute("transform", e), !(e = kl.transform.baseVal.consolidate())) ? vp : (e = e.matrix, vb(e.a, e.b, e.c, e.d, e.e, e.f))
}

function yb(e, t, n, r) {
    function o(u) {
        return u.length ? u.pop() + " " : ""
    }

    function s(u, d, f, p, h, x) {
        if (u !== f || d !== p) {
            var m = h.push("translate(", null, t, null, n);
            x.push({i: m - 4, x: Sr(u, f)}, {i: m - 2, x: Sr(d, p)})
        } else (f || p) && h.push("translate(" + f + t + p + n)
    }

    function i(u, d, f, p) {
        u !== d ? (u - d > 180 ? d += 360 : d - u > 180 && (u += 360), p.push({
            i: f.push(o(f) + "rotate(", null, r) - 2,
            x: Sr(u, d)
        })) : d && f.push(o(f) + "rotate(" + d + r)
    }

    function a(u, d, f, p) {
        u !== d ? p.push({i: f.push(o(f) + "skewX(", null, r) - 2, x: Sr(u, d)}) : d && f.push(o(f) + "skewX(" + d + r)
    }

    function l(u, d, f, p, h, x) {
        if (u !== f || d !== p) {
            var m = h.push(o(h) + "scale(", null, ",", null, ")");
            x.push({i: m - 4, x: Sr(u, f)}, {i: m - 2, x: Sr(d, p)})
        } else (f !== 1 || p !== 1) && h.push(o(h) + "scale(" + f + "," + p + ")")
    }

    return function (u, d) {
        var f = [], p = [];
        return u = e(u), d = e(d), s(u.translateX, u.translateY, d.translateX, d.translateY, f, p), i(u.rotate, d.rotate, f, p), a(u.skewX, d.skewX, f, p), l(u.scaleX, u.scaleY, d.scaleX, d.scaleY, f, p), u = d = null, function (h) {
            for (var x = -1, m = p.length, b; ++x < m;) f[(b = p[x]).i] = b.x(h);
            return f.join("")
        }
    }
}

var bI = yb(xI, "px, ", "px)", "deg)"), CI = yb(wI, ", ", ")", ")"), SI = 1e-12;

function Wv(e) {
    return ((e = Math.exp(e)) + 1 / e) / 2
}

function NI(e) {
    return ((e = Math.exp(e)) - 1 / e) / 2
}

function EI(e) {
    return ((e = Math.exp(2 * e)) - 1) / (e + 1)
}

const kI = function e(t, n, r) {
    function o(s, i) {
        var a = s[0], l = s[1], u = s[2], d = i[0], f = i[1], p = i[2], h = d - a, x = f - l, m = h * h + x * x, b, y;
        if (m < SI) y = Math.log(p / u) / t, b = function (E) {
            return [a + E * h, l + E * x, u * Math.exp(t * E * y)]
        }; else {
            var w = Math.sqrt(m), v = (p * p - u * u + r * m) / (2 * u * n * w),
                C = (p * p - u * u - r * m) / (2 * p * n * w), S = Math.log(Math.sqrt(v * v + 1) - v),
                N = Math.log(Math.sqrt(C * C + 1) - C);
            y = (N - S) / t, b = function (E) {
                var T = E * y, O = Wv(S), D = u / (n * w) * (O * EI(t * T + S) - NI(S));
                return [a + D * h, l + D * x, u * O / Wv(t * T + S)]
            }
        }
        return b.duration = y * 1e3 * t / Math.SQRT2, b
    }

    return o.rho = function (s) {
        var i = Math.max(.001, +s), a = i * i, l = a * a;
        return e(i, a, l)
    }, o
}(Math.SQRT2, 2, 4);
var Bs = 0, Ni = 0, vi = 0, xb = 1e3, $c, Ei, Lc = 0, Ro = 0, ju = 0,
    fa = typeof performance == "object" && performance.now ? performance : Date,
    wb = typeof window == "object" && window.requestAnimationFrame ? window.requestAnimationFrame.bind(window) : function (e) {
        setTimeout(e, 17)
    };

function am() {
    return Ro || (wb(_I), Ro = fa.now() + ju)
}

function _I() {
    Ro = 0
}

function Fc() {
    this._call = this._time = this._next = null
}

Fc.prototype = bb.prototype = {
    constructor: Fc, restart: function (e, t, n) {
        if (typeof e != "function") throw new TypeError("callback is not a function");
        n = (n == null ? am() : +n) + (t == null ? 0 : +t), !this._next && Ei !== this && (Ei ? Ei._next = this : $c = this, Ei = this), this._call = e, this._time = n, yp()
    }, stop: function () {
        this._call && (this._call = null, this._time = 1 / 0, yp())
    }
};

function bb(e, t, n) {
    var r = new Fc;
    return r.restart(e, t, n), r
}

function jI() {
    am(), ++Bs;
    for (var e = $c, t; e;) (t = Ro - e._time) >= 0 && e._call.call(void 0, t), e = e._next;
    --Bs
}

function Kv() {
    Ro = (Lc = fa.now()) + ju, Bs = Ni = 0;
    try {
        jI()
    } finally {
        Bs = 0, TI(), Ro = 0
    }
}

function PI() {
    var e = fa.now(), t = e - Lc;
    t > xb && (ju -= t, Lc = e)
}

function TI() {
    for (var e, t = $c, n, r = 1 / 0; t;) t._call ? (r > t._time && (r = t._time), e = t, t = t._next) : (n = t._next, t._next = null, t = e ? e._next = n : $c = n);
    Ei = e, yp(r)
}

function yp(e) {
    if (!Bs) {
        Ni && (Ni = clearTimeout(Ni));
        var t = e - Ro;
        t > 24 ? (e < 1 / 0 && (Ni = setTimeout(Kv, e - fa.now() - ju)), vi && (vi = clearInterval(vi))) : (vi || (Lc = fa.now(), vi = setInterval(PI, xb)), Bs = 1, wb(Kv))
    }
}

function Yv(e, t, n) {
    var r = new Fc;
    return t = t == null ? 0 : +t, r.restart(o => {
        r.stop(), e(o + t)
    }, t, n), r
}

var MI = ku("start", "end", "cancel", "interrupt"), RI = [], Cb = 0, Gv = 1, xp = 2, Jl = 3, Xv = 4, wp = 5, ec = 6;

function Pu(e, t, n, r, o, s) {
    var i = e.__transition;
    if (!i) e.__transition = {}; else if (n in i) return;
    AI(e, n, {
        name: t,
        index: r,
        group: o,
        on: MI,
        tween: RI,
        time: s.time,
        delay: s.delay,
        duration: s.duration,
        ease: s.ease,
        timer: null,
        state: Cb
    })
}

function lm(e, t) {
    var n = gn(e, t);
    if (n.state > Cb) throw new Error("too late; already scheduled");
    return n
}

function Dn(e, t) {
    var n = gn(e, t);
    if (n.state > Jl) throw new Error("too late; already running");
    return n
}

function gn(e, t) {
    var n = e.__transition;
    if (!n || !(n = n[t])) throw new Error("transition not found");
    return n
}

function AI(e, t, n) {
    var r = e.__transition, o;
    r[t] = n, n.timer = bb(s, 0, n.time);

    function s(u) {
        n.state = Gv, n.timer.restart(i, n.delay, n.time), n.delay <= u && i(u - n.delay)
    }

    function i(u) {
        var d, f, p, h;
        if (n.state !== Gv) return l();
        for (d in r) if (h = r[d], h.name === n.name) {
            if (h.state === Jl) return Yv(i);
            h.state === Xv ? (h.state = ec, h.timer.stop(), h.on.call("interrupt", e, e.__data__, h.index, h.group), delete r[d]) : +d < t && (h.state = ec, h.timer.stop(), h.on.call("cancel", e, e.__data__, h.index, h.group), delete r[d])
        }
        if (Yv(function () {
            n.state === Jl && (n.state = Xv, n.timer.restart(a, n.delay, n.time), a(u))
        }), n.state = xp, n.on.call("start", e, e.__data__, n.index, n.group), n.state === xp) {
            for (n.state = Jl, o = new Array(p = n.tween.length), d = 0, f = -1; d < p; ++d) (h = n.tween[d].value.call(e, e.__data__, n.index, n.group)) && (o[++f] = h);
            o.length = f + 1
        }
    }

    function a(u) {
        for (var d = u < n.duration ? n.ease.call(null, u / n.duration) : (n.timer.restart(l), n.state = wp, 1), f = -1, p = o.length; ++f < p;) o[f].call(e, d);
        n.state === wp && (n.on.call("end", e, e.__data__, n.index, n.group), l())
    }

    function l() {
        n.state = ec, n.timer.stop(), delete r[t];
        for (var u in r) return;
        delete e.__transition
    }
}

function tc(e, t) {
    var n = e.__transition, r, o, s = !0, i;
    if (n) {
        t = t == null ? null : t + "";
        for (i in n) {
            if ((r = n[i]).name !== t) {
                s = !1;
                continue
            }
            o = r.state > xp && r.state < wp, r.state = ec, r.timer.stop(), r.on.call(o ? "interrupt" : "cancel", e, e.__data__, r.index, r.group), delete n[i]
        }
        s && delete e.__transition
    }
}

function II(e) {
    return this.each(function () {
        tc(this, e)
    })
}

function OI(e, t) {
    var n, r;
    return function () {
        var o = Dn(this, e), s = o.tween;
        if (s !== n) {
            r = n = s;
            for (var i = 0, a = r.length; i < a; ++i) if (r[i].name === t) {
                r = r.slice(), r.splice(i, 1);
                break
            }
        }
        o.tween = r
    }
}

function DI(e, t, n) {
    var r, o;
    if (typeof n != "function") throw new Error;
    return function () {
        var s = Dn(this, e), i = s.tween;
        if (i !== r) {
            o = (r = i).slice();
            for (var a = {name: t, value: n}, l = 0, u = o.length; l < u; ++l) if (o[l].name === t) {
                o[l] = a;
                break
            }
            l === u && o.push(a)
        }
        s.tween = o
    }
}

function $I(e, t) {
    var n = this._id;
    if (e += "", arguments.length < 2) {
        for (var r = gn(this.node(), n).tween, o = 0, s = r.length, i; o < s; ++o) if ((i = r[o]).name === e) return i.value;
        return null
    }
    return this.each((t == null ? OI : DI)(n, e, t))
}

function cm(e, t, n) {
    var r = e._id;
    return e.each(function () {
        var o = Dn(this, r);
        (o.value || (o.value = {}))[t] = n.apply(this, arguments)
    }), function (o) {
        return gn(o, r).value[t]
    }
}

function Sb(e, t) {
    var n;
    return (typeof t == "number" ? Sr : t instanceof da ? Hv : (n = da(t)) ? (t = n, Hv) : yI)(e, t)
}

function LI(e) {
    return function () {
        this.removeAttribute(e)
    }
}

function FI(e) {
    return function () {
        this.removeAttributeNS(e.space, e.local)
    }
}

function zI(e, t, n) {
    var r, o = n + "", s;
    return function () {
        var i = this.getAttribute(e);
        return i === o ? null : i === r ? s : s = t(r = i, n)
    }
}

function BI(e, t, n) {
    var r, o = n + "", s;
    return function () {
        var i = this.getAttributeNS(e.space, e.local);
        return i === o ? null : i === r ? s : s = t(r = i, n)
    }
}

function VI(e, t, n) {
    var r, o, s;
    return function () {
        var i, a = n(this), l;
        return a == null ? void this.removeAttribute(e) : (i = this.getAttribute(e), l = a + "", i === l ? null : i === r && l === o ? s : (o = l, s = t(r = i, a)))
    }
}

function HI(e, t, n) {
    var r, o, s;
    return function () {
        var i, a = n(this), l;
        return a == null ? void this.removeAttributeNS(e.space, e.local) : (i = this.getAttributeNS(e.space, e.local), l = a + "", i === l ? null : i === r && l === o ? s : (o = l, s = t(r = i, a)))
    }
}

function UI(e, t) {
    var n = _u(e), r = n === "transform" ? CI : Sb;
    return this.attrTween(e, typeof t == "function" ? (n.local ? HI : VI)(n, r, cm(this, "attr." + e, t)) : t == null ? (n.local ? FI : LI)(n) : (n.local ? BI : zI)(n, r, t))
}

function WI(e, t) {
    return function (n) {
        this.setAttribute(e, t.call(this, n))
    }
}

function KI(e, t) {
    return function (n) {
        this.setAttributeNS(e.space, e.local, t.call(this, n))
    }
}

function YI(e, t) {
    var n, r;

    function o() {
        var s = t.apply(this, arguments);
        return s !== r && (n = (r = s) && KI(e, s)), n
    }

    return o._value = t, o
}

function GI(e, t) {
    var n, r;

    function o() {
        var s = t.apply(this, arguments);
        return s !== r && (n = (r = s) && WI(e, s)), n
    }

    return o._value = t, o
}

function XI(e, t) {
    var n = "attr." + e;
    if (arguments.length < 2) return (n = this.tween(n)) && n._value;
    if (t == null) return this.tween(n, null);
    if (typeof t != "function") throw new Error;
    var r = _u(e);
    return this.tween(n, (r.local ? YI : GI)(r, t))
}

function qI(e, t) {
    return function () {
        lm(this, e).delay = +t.apply(this, arguments)
    }
}

function QI(e, t) {
    return t = +t, function () {
        lm(this, e).delay = t
    }
}

function ZI(e) {
    var t = this._id;
    return arguments.length ? this.each((typeof e == "function" ? qI : QI)(t, e)) : gn(this.node(), t).delay
}

function JI(e, t) {
    return function () {
        Dn(this, e).duration = +t.apply(this, arguments)
    }
}

function e4(e, t) {
    return t = +t, function () {
        Dn(this, e).duration = t
    }
}

function t4(e) {
    var t = this._id;
    return arguments.length ? this.each((typeof e == "function" ? JI : e4)(t, e)) : gn(this.node(), t).duration
}

function n4(e, t) {
    if (typeof t != "function") throw new Error;
    return function () {
        Dn(this, e).ease = t
    }
}

function r4(e) {
    var t = this._id;
    return arguments.length ? this.each(n4(t, e)) : gn(this.node(), t).ease
}

function o4(e, t) {
    return function () {
        var n = t.apply(this, arguments);
        if (typeof n != "function") throw new Error;
        Dn(this, e).ease = n
    }
}

function s4(e) {
    if (typeof e != "function") throw new Error;
    return this.each(o4(this._id, e))
}

function i4(e) {
    typeof e != "function" && (e = eb(e));
    for (var t = this._groups, n = t.length, r = new Array(n), o = 0; o < n; ++o) for (var s = t[o], i = s.length, a = r[o] = [], l, u = 0; u < i; ++u) (l = s[u]) && e.call(l, l.__data__, u, s) && a.push(l);
    return new rr(r, this._parents, this._name, this._id)
}

function a4(e) {
    if (e._id !== this._id) throw new Error;
    for (var t = this._groups, n = e._groups, r = t.length, o = n.length, s = Math.min(r, o), i = new Array(r), a = 0; a < s; ++a) for (var l = t[a], u = n[a], d = l.length, f = i[a] = new Array(d), p, h = 0; h < d; ++h) (p = l[h] || u[h]) && (f[h] = p);
    for (; a < r; ++a) i[a] = t[a];
    return new rr(i, this._parents, this._name, this._id)
}

function l4(e) {
    return (e + "").trim().split(/^|\s+/).every(function (t) {
        var n = t.indexOf(".");
        return n >= 0 && (t = t.slice(0, n)), !t || t === "start"
    })
}

function c4(e, t, n) {
    var r, o, s = l4(t) ? lm : Dn;
    return function () {
        var i = s(this, e), a = i.on;
        a !== r && (o = (r = a).copy()).on(t, n), i.on = o
    }
}

function u4(e, t) {
    var n = this._id;
    return arguments.length < 2 ? gn(this.node(), n).on.on(e) : this.each(c4(n, e, t))
}

function d4(e) {
    return function () {
        var t = this.parentNode;
        for (var n in this.__transition) if (+n !== e) return;
        t && t.removeChild(this)
    }
}

function f4() {
    return this.on("end.remove", d4(this._id))
}

function p4(e) {
    var t = this._name, n = this._id;
    typeof e != "function" && (e = om(e));
    for (var r = this._groups, o = r.length, s = new Array(o), i = 0; i < o; ++i) for (var a = r[i], l = a.length, u = s[i] = new Array(l), d, f, p = 0; p < l; ++p) (d = a[p]) && (f = e.call(d, d.__data__, p, a)) && ("__data__" in d && (f.__data__ = d.__data__), u[p] = f, Pu(u[p], t, n, p, u, gn(d, n)));
    return new rr(s, this._parents, t, n)
}

function h4(e) {
    var t = this._name, n = this._id;
    typeof e != "function" && (e = J1(e));
    for (var r = this._groups, o = r.length, s = [], i = [], a = 0; a < o; ++a) for (var l = r[a], u = l.length, d, f = 0; f < u; ++f) if (d = l[f]) {
        for (var p = e.call(d, d.__data__, f, l), h, x = gn(d, n), m = 0, b = p.length; m < b; ++m) (h = p[m]) && Pu(h, t, n, m, p, x);
        s.push(p), i.push(d)
    }
    return new rr(s, i, t, n)
}

var m4 = Ra.prototype.constructor;

function g4() {
    return new m4(this._groups, this._parents)
}

function v4(e, t) {
    var n, r, o;
    return function () {
        var s = zs(this, e), i = (this.style.removeProperty(e), zs(this, e));
        return s === i ? null : s === n && i === r ? o : o = t(n = s, r = i)
    }
}

function Nb(e) {
    return function () {
        this.style.removeProperty(e)
    }
}

function y4(e, t, n) {
    var r, o = n + "", s;
    return function () {
        var i = zs(this, e);
        return i === o ? null : i === r ? s : s = t(r = i, n)
    }
}

function x4(e, t, n) {
    var r, o, s;
    return function () {
        var i = zs(this, e), a = n(this), l = a + "";
        return a == null && (l = a = (this.style.removeProperty(e), zs(this, e))), i === l ? null : i === r && l === o ? s : (o = l, s = t(r = i, a))
    }
}

function w4(e, t) {
    var n, r, o, s = "style." + t, i = "end." + s, a;
    return function () {
        var l = Dn(this, e), u = l.on, d = l.value[s] == null ? a || (a = Nb(t)) : void 0;
        (u !== n || o !== d) && (r = (n = u).copy()).on(i, o = d), l.on = r
    }
}

function b4(e, t, n) {
    var r = (e += "") == "transform" ? bI : Sb;
    return t == null ? this.styleTween(e, v4(e, r)).on("end.style." + e, Nb(e)) : typeof t == "function" ? this.styleTween(e, x4(e, r, cm(this, "style." + e, t))).each(w4(this._id, e)) : this.styleTween(e, y4(e, r, t), n).on("end.style." + e, null)
}

function C4(e, t, n) {
    return function (r) {
        this.style.setProperty(e, t.call(this, r), n)
    }
}

function S4(e, t, n) {
    var r, o;

    function s() {
        var i = t.apply(this, arguments);
        return i !== o && (r = (o = i) && C4(e, i, n)), r
    }

    return s._value = t, s
}

function N4(e, t, n) {
    var r = "style." + (e += "");
    if (arguments.length < 2) return (r = this.tween(r)) && r._value;
    if (t == null) return this.tween(r, null);
    if (typeof t != "function") throw new Error;
    return this.tween(r, S4(e, t, n ?? ""))
}

function E4(e) {
    return function () {
        this.textContent = e
    }
}

function k4(e) {
    return function () {
        var t = e(this);
        this.textContent = t ?? ""
    }
}

function _4(e) {
    return this.tween("text", typeof e == "function" ? k4(cm(this, "text", e)) : E4(e == null ? "" : e + ""))
}

function j4(e) {
    return function (t) {
        this.textContent = e.call(this, t)
    }
}

function P4(e) {
    var t, n;

    function r() {
        var o = e.apply(this, arguments);
        return o !== n && (t = (n = o) && j4(o)), t
    }

    return r._value = e, r
}

function T4(e) {
    var t = "text";
    if (arguments.length < 1) return (t = this.tween(t)) && t._value;
    if (e == null) return this.tween(t, null);
    if (typeof e != "function") throw new Error;
    return this.tween(t, P4(e))
}

function M4() {
    for (var e = this._name, t = this._id, n = Eb(), r = this._groups, o = r.length, s = 0; s < o; ++s) for (var i = r[s], a = i.length, l, u = 0; u < a; ++u) if (l = i[u]) {
        var d = gn(l, t);
        Pu(l, e, n, u, i, {time: d.time + d.delay + d.duration, delay: 0, duration: d.duration, ease: d.ease})
    }
    return new rr(r, this._parents, e, n)
}

function R4() {
    var e, t, n = this, r = n._id, o = n.size();
    return new Promise(function (s, i) {
        var a = {value: i}, l = {
            value: function () {
                --o === 0 && s()
            }
        };
        n.each(function () {
            var u = Dn(this, r), d = u.on;
            d !== e && (t = (e = d).copy(), t._.cancel.push(a), t._.interrupt.push(a), t._.end.push(l)), u.on = t
        }), o === 0 && s()
    })
}

var A4 = 0;

function rr(e, t, n, r) {
    this._groups = e, this._parents = t, this._name = n, this._id = r
}

function Eb() {
    return ++A4
}

var Bn = Ra.prototype;
rr.prototype = {
    constructor: rr,
    select: p4,
    selectAll: h4,
    selectChild: Bn.selectChild,
    selectChildren: Bn.selectChildren,
    filter: i4,
    merge: a4,
    selection: g4,
    transition: M4,
    call: Bn.call,
    nodes: Bn.nodes,
    node: Bn.node,
    size: Bn.size,
    empty: Bn.empty,
    each: Bn.each,
    on: u4,
    attr: UI,
    attrTween: XI,
    style: b4,
    styleTween: N4,
    text: _4,
    textTween: T4,
    remove: f4,
    tween: $I,
    delay: ZI,
    duration: t4,
    ease: r4,
    easeVarying: s4,
    end: R4,
    [Symbol.iterator]: Bn[Symbol.iterator]
};

function I4(e) {
    return ((e *= 2) <= 1 ? e * e * e : (e -= 2) * e * e + 2) / 2
}

var O4 = {time: null, delay: 0, duration: 250, ease: I4};

function D4(e, t) {
    for (var n; !(n = e.__transition) || !(n = n[t]);) if (!(e = e.parentNode)) throw new Error(`transition ${t} not found`);
    return n
}

function $4(e) {
    var t, n;
    e instanceof rr ? (t = e._id, e = e._name) : (t = Eb(), (n = O4).time = am(), e = e == null ? null : e + "");
    for (var r = this._groups, o = r.length, s = 0; s < o; ++s) for (var i = r[s], a = i.length, l, u = 0; u < a; ++u) (l = i[u]) && Pu(l, e, t, u, i, n || D4(l, t));
    return new rr(r, this._parents, e, t)
}

Ra.prototype.interrupt = II;
Ra.prototype.transition = $4;
const _l = e => () => e;

function L4(e, {sourceEvent: t, target: n, transform: r, dispatch: o}) {
    Object.defineProperties(this, {
        type: {value: e, enumerable: !0, configurable: !0},
        sourceEvent: {value: t, enumerable: !0, configurable: !0},
        target: {value: n, enumerable: !0, configurable: !0},
        transform: {value: r, enumerable: !0, configurable: !0},
        _: {value: o}
    })
}

function Gn(e, t, n) {
    this.k = e, this.x = t, this.y = n
}

Gn.prototype = {
    constructor: Gn, scale: function (e) {
        return e === 1 ? this : new Gn(this.k * e, this.x, this.y)
    }, translate: function (e, t) {
        return e === 0 & t === 0 ? this : new Gn(this.k, this.x + this.k * e, this.y + this.k * t)
    }, apply: function (e) {
        return [e[0] * this.k + this.x, e[1] * this.k + this.y]
    }, applyX: function (e) {
        return e * this.k + this.x
    }, applyY: function (e) {
        return e * this.k + this.y
    }, invert: function (e) {
        return [(e[0] - this.x) / this.k, (e[1] - this.y) / this.k]
    }, invertX: function (e) {
        return (e - this.x) / this.k
    }, invertY: function (e) {
        return (e - this.y) / this.k
    }, rescaleX: function (e) {
        return e.copy().domain(e.range().map(this.invertX, this).map(e.invert, e))
    }, rescaleY: function (e) {
        return e.copy().domain(e.range().map(this.invertY, this).map(e.invert, e))
    }, toString: function () {
        return "translate(" + this.x + "," + this.y + ") scale(" + this.k + ")"
    }
};
var Tu = new Gn(1, 0, 0);
kb.prototype = Gn.prototype;

function kb(e) {
    for (; !e.__zoom;) if (!(e = e.parentNode)) return Tu;
    return e.__zoom
}

function Ud(e) {
    e.stopImmediatePropagation()
}

function yi(e) {
    e.preventDefault(), e.stopImmediatePropagation()
}

function F4(e) {
    return (!e.ctrlKey || e.type === "wheel") && !e.button
}

function z4() {
    var e = this;
    return e instanceof SVGElement ? (e = e.ownerSVGElement || e, e.hasAttribute("viewBox") ? (e = e.viewBox.baseVal, [[e.x, e.y], [e.x + e.width, e.y + e.height]]) : [[0, 0], [e.width.baseVal.value, e.height.baseVal.value]]) : [[0, 0], [e.clientWidth, e.clientHeight]]
}

function qv() {
    return this.__zoom || Tu
}

function B4(e) {
    return -e.deltaY * (e.deltaMode === 1 ? .05 : e.deltaMode ? 1 : .002) * (e.ctrlKey ? 10 : 1)
}

function V4() {
    return navigator.maxTouchPoints || "ontouchstart" in this
}

function H4(e, t, n) {
    var r = e.invertX(t[0][0]) - n[0][0], o = e.invertX(t[1][0]) - n[1][0], s = e.invertY(t[0][1]) - n[0][1],
        i = e.invertY(t[1][1]) - n[1][1];
    return e.translate(o > r ? (r + o) / 2 : Math.min(0, r) || Math.max(0, o), i > s ? (s + i) / 2 : Math.min(0, s) || Math.max(0, i))
}

function _b() {
    var e = F4, t = z4, n = H4, r = B4, o = V4, s = [0, 1 / 0], i = [[-1 / 0, -1 / 0], [1 / 0, 1 / 0]], a = 250, l = kI,
        u = ku("start", "zoom", "end"), d, f, p, h = 500, x = 150, m = 0, b = 10;

    function y(k) {
        k.property("__zoom", qv).on("wheel.zoom", T, {passive: !1}).on("mousedown.zoom", O).on("dblclick.zoom", D).filter(o).on("touchstart.zoom", L).on("touchmove.zoom", $).on("touchend.zoom touchcancel.zoom", B).style("-webkit-tap-highlight-color", "rgba(0,0,0,0)")
    }

    y.transform = function (k, M, j, A) {
        var P = k.selection ? k.selection() : k;
        P.property("__zoom", qv), k !== P ? S(k, M, j, A) : P.interrupt().each(function () {
            N(this, arguments).event(A).start().zoom(null, typeof M == "function" ? M.apply(this, arguments) : M).end()
        })
    }, y.scaleBy = function (k, M, j, A) {
        y.scaleTo(k, function () {
            var P = this.__zoom.k, _ = typeof M == "function" ? M.apply(this, arguments) : M;
            return P * _
        }, j, A)
    }, y.scaleTo = function (k, M, j, A) {
        y.transform(k, function () {
            var P = t.apply(this, arguments), _ = this.__zoom,
                R = j == null ? C(P) : typeof j == "function" ? j.apply(this, arguments) : j, z = _.invert(R),
                I = typeof M == "function" ? M.apply(this, arguments) : M;
            return n(v(w(_, I), R, z), P, i)
        }, j, A)
    }, y.translateBy = function (k, M, j, A) {
        y.transform(k, function () {
            return n(this.__zoom.translate(typeof M == "function" ? M.apply(this, arguments) : M, typeof j == "function" ? j.apply(this, arguments) : j), t.apply(this, arguments), i)
        }, null, A)
    }, y.translateTo = function (k, M, j, A, P) {
        y.transform(k, function () {
            var _ = t.apply(this, arguments), R = this.__zoom,
                z = A == null ? C(_) : typeof A == "function" ? A.apply(this, arguments) : A;
            return n(Tu.translate(z[0], z[1]).scale(R.k).translate(typeof M == "function" ? -M.apply(this, arguments) : -M, typeof j == "function" ? -j.apply(this, arguments) : -j), _, i)
        }, A, P)
    };

    function w(k, M) {
        return M = Math.max(s[0], Math.min(s[1], M)), M === k.k ? k : new Gn(M, k.x, k.y)
    }

    function v(k, M, j) {
        var A = M[0] - j[0] * k.k, P = M[1] - j[1] * k.k;
        return A === k.x && P === k.y ? k : new Gn(k.k, A, P)
    }

    function C(k) {
        return [(+k[0][0] + +k[1][0]) / 2, (+k[0][1] + +k[1][1]) / 2]
    }

    function S(k, M, j, A) {
        k.on("start.zoom", function () {
            N(this, arguments).event(A).start()
        }).on("interrupt.zoom end.zoom", function () {
            N(this, arguments).event(A).end()
        }).tween("zoom", function () {
            var P = this, _ = arguments, R = N(P, _).event(A), z = t.apply(P, _),
                I = j == null ? C(z) : typeof j == "function" ? j.apply(P, _) : j,
                V = Math.max(z[1][0] - z[0][0], z[1][1] - z[0][1]), H = P.__zoom,
                U = typeof M == "function" ? M.apply(P, _) : M,
                K = l(H.invert(I).concat(V / H.k), U.invert(I).concat(V / U.k));
            return function (F) {
                if (F === 1) F = U; else {
                    var Y = K(F), X = V / Y[2];
                    F = new Gn(X, I[0] - Y[0] * X, I[1] - Y[1] * X)
                }
                R.zoom(null, F)
            }
        })
    }

    function N(k, M, j) {
        return !j && k.__zooming || new E(k, M)
    }

    function E(k, M) {
        this.that = k, this.args = M, this.active = 0, this.sourceEvent = null, this.extent = t.apply(k, M), this.taps = 0
    }

    E.prototype = {
        event: function (k) {
            return k && (this.sourceEvent = k), this
        }, start: function () {
            return ++this.active === 1 && (this.that.__zooming = this, this.emit("start")), this
        }, zoom: function (k, M) {
            return this.mouse && k !== "mouse" && (this.mouse[1] = M.invert(this.mouse[0])), this.touch0 && k !== "touch" && (this.touch0[1] = M.invert(this.touch0[0])), this.touch1 && k !== "touch" && (this.touch1[1] = M.invert(this.touch1[0])), this.that.__zoom = M, this.emit("zoom"), this
        }, end: function () {
            return --this.active === 0 && (delete this.that.__zooming, this.emit("end")), this
        }, emit: function (k) {
            var M = jt(this.that).datum();
            u.call(k, this.that, new L4(k, {
                sourceEvent: this.sourceEvent,
                target: y,
                transform: this.that.__zoom,
                dispatch: u
            }), M)
        }
    };

    function T(k, ...M) {
        if (!e.apply(this, arguments)) return;
        var j = N(this, M).event(k), A = this.__zoom,
            P = Math.max(s[0], Math.min(s[1], A.k * Math.pow(2, r.apply(this, arguments)))), _ = rn(k);
        if (j.wheel) (j.mouse[0][0] !== _[0] || j.mouse[0][1] !== _[1]) && (j.mouse[1] = A.invert(j.mouse[0] = _)), clearTimeout(j.wheel); else {
            if (A.k === P) return;
            j.mouse = [_, A.invert(_)], tc(this), j.start()
        }
        yi(k), j.wheel = setTimeout(R, x), j.zoom("mouse", n(v(w(A, P), j.mouse[0], j.mouse[1]), j.extent, i));

        function R() {
            j.wheel = null, j.end()
        }
    }

    function O(k, ...M) {
        if (p || !e.apply(this, arguments)) return;
        var j = k.currentTarget, A = N(this, M, !0).event(k),
            P = jt(k.view).on("mousemove.zoom", I, !0).on("mouseup.zoom", V, !0), _ = rn(k, j), R = k.clientX,
            z = k.clientY;
        ub(k.view), Ud(k), A.mouse = [_, this.__zoom.invert(_)], tc(this), A.start();

        function I(H) {
            if (yi(H), !A.moved) {
                var U = H.clientX - R, K = H.clientY - z;
                A.moved = U * U + K * K > m
            }
            A.event(H).zoom("mouse", n(v(A.that.__zoom, A.mouse[0] = rn(H, j), A.mouse[1]), A.extent, i))
        }

        function V(H) {
            P.on("mousemove.zoom mouseup.zoom", null), db(H.view, A.moved), yi(H), A.event(H).end()
        }
    }

    function D(k, ...M) {
        if (e.apply(this, arguments)) {
            var j = this.__zoom, A = rn(k.changedTouches ? k.changedTouches[0] : k, this), P = j.invert(A),
                _ = j.k * (k.shiftKey ? .5 : 2), R = n(v(w(j, _), A, P), t.apply(this, M), i);
            yi(k), a > 0 ? jt(this).transition().duration(a).call(S, R, A, k) : jt(this).call(y.transform, R, A, k)
        }
    }

    function L(k, ...M) {
        if (e.apply(this, arguments)) {
            var j = k.touches, A = j.length, P = N(this, M, k.changedTouches.length === A).event(k), _, R, z, I;
            for (Ud(k), R = 0; R < A; ++R) z = j[R], I = rn(z, this), I = [I, this.__zoom.invert(I), z.identifier], P.touch0 ? !P.touch1 && P.touch0[2] !== I[2] && (P.touch1 = I, P.taps = 0) : (P.touch0 = I, _ = !0, P.taps = 1 + !!d);
            d && (d = clearTimeout(d)), _ && (P.taps < 2 && (f = I[0], d = setTimeout(function () {
                d = null
            }, h)), tc(this), P.start())
        }
    }

    function $(k, ...M) {
        if (this.__zooming) {
            var j = N(this, M).event(k), A = k.changedTouches, P = A.length, _, R, z, I;
            for (yi(k), _ = 0; _ < P; ++_) R = A[_], z = rn(R, this), j.touch0 && j.touch0[2] === R.identifier ? j.touch0[0] = z : j.touch1 && j.touch1[2] === R.identifier && (j.touch1[0] = z);
            if (R = j.that.__zoom, j.touch1) {
                var V = j.touch0[0], H = j.touch0[1], U = j.touch1[0], K = j.touch1[1],
                    F = (F = U[0] - V[0]) * F + (F = U[1] - V[1]) * F,
                    Y = (Y = K[0] - H[0]) * Y + (Y = K[1] - H[1]) * Y;
                R = w(R, Math.sqrt(F / Y)), z = [(V[0] + U[0]) / 2, (V[1] + U[1]) / 2], I = [(H[0] + K[0]) / 2, (H[1] + K[1]) / 2]
            } else if (j.touch0) z = j.touch0[0], I = j.touch0[1]; else return;
            j.zoom("touch", n(v(R, z, I), j.extent, i))
        }
    }

    function B(k, ...M) {
        if (this.__zooming) {
            var j = N(this, M).event(k), A = k.changedTouches, P = A.length, _, R;
            for (Ud(k), p && clearTimeout(p), p = setTimeout(function () {
                p = null
            }, h), _ = 0; _ < P; ++_) R = A[_], j.touch0 && j.touch0[2] === R.identifier ? delete j.touch0 : j.touch1 && j.touch1[2] === R.identifier && delete j.touch1;
            if (j.touch1 && !j.touch0 && (j.touch0 = j.touch1, delete j.touch1), j.touch0) j.touch0[1] = this.__zoom.invert(j.touch0[0]); else if (j.end(), j.taps === 2 && (R = rn(R, this), Math.hypot(f[0] - R[0], f[1] - R[1]) < b)) {
                var z = jt(this).on("dblclick.zoom");
                z && z.apply(this, arguments)
            }
        }
    }

    return y.wheelDelta = function (k) {
        return arguments.length ? (r = typeof k == "function" ? k : _l(+k), y) : r
    }, y.filter = function (k) {
        return arguments.length ? (e = typeof k == "function" ? k : _l(!!k), y) : e
    }, y.touchable = function (k) {
        return arguments.length ? (o = typeof k == "function" ? k : _l(!!k), y) : o
    }, y.extent = function (k) {
        return arguments.length ? (t = typeof k == "function" ? k : _l([[+k[0][0], +k[0][1]], [+k[1][0], +k[1][1]]]), y) : t
    }, y.scaleExtent = function (k) {
        return arguments.length ? (s[0] = +k[0], s[1] = +k[1], y) : [s[0], s[1]]
    }, y.translateExtent = function (k) {
        return arguments.length ? (i[0][0] = +k[0][0], i[1][0] = +k[1][0], i[0][1] = +k[0][1], i[1][1] = +k[1][1], y) : [[i[0][0], i[0][1]], [i[1][0], i[1][1]]]
    }, y.constrain = function (k) {
        return arguments.length ? (n = k, y) : n
    }, y.duration = function (k) {
        return arguments.length ? (a = +k, y) : a
    }, y.interpolate = function (k) {
        return arguments.length ? (l = k, y) : l
    }, y.on = function () {
        var k = u.on.apply(u, arguments);
        return k === u ? y : k
    }, y.clickDistance = function (k) {
        return arguments.length ? (m = (k = +k) * k, y) : Math.sqrt(m)
    }, y.tapDistance = function (k) {
        return arguments.length ? (b = +k, y) : b
    }, y
}

const In = {
        error001: () => "[React Flow]: Seems like you have not used zustand provider as an ancestor. Help: https://reactflow.dev/error#001",
        error002: () => "It looks like you've created a new nodeTypes or edgeTypes object. If this wasn't on purpose please define the nodeTypes/edgeTypes outside of the component or memoize them.",
        error003: e => `Node type "${e}" not found. Using fallback type "default".`,
        error004: () => "The React Flow parent container needs a width and a height to render the graph.",
        error005: () => "Only child nodes can use a parent extent.",
        error006: () => "Can't create edge. An edge needs a source and a target.",
        error007: e => `The old edge with id=${e} does not exist.`,
        error009: e => `Marker type "${e}" doesn't exist.`,
        error008: (e, {
            id: t,
            sourceHandle: n,
            targetHandle: r
        }) => `Couldn't create edge for ${e} handle id: "${e === "source" ? n : r}", edge id: ${t}.`,
        error010: () => "Handle: No node id found. Make sure to only use a Handle inside a custom Node.",
        error011: e => `Edge type "${e}" not found. Using fallback type "default".`,
        error012: e => `Node with id "${e}" does not exist, it may have been removed. This can happen when a node is deleted before the "onNodeClick" handler is called.`,
        error013: (e = "react") => `It seems that you haven't loaded the styles. Please import '@xyflow/${e}/dist/style.css' or base.css to make sure everything is working properly.`,
        error014: () => "useNodeConnections: No node ID found. Call useNodeConnections inside a custom Node or provide a node ID.",
        error015: () => "It seems that you are trying to drag a node that is not initialized. Please use onNodesChange as explained in the docs."
    }, pa = [[Number.NEGATIVE_INFINITY, Number.NEGATIVE_INFINITY], [Number.POSITIVE_INFINITY, Number.POSITIVE_INFINITY]],
    jb = ["Enter", " ", "Escape"];
var Vs;
(function (e) {
    e.Strict = "strict", e.Loose = "loose"
})(Vs || (Vs = {}));
var No;
(function (e) {
    e.Free = "free", e.Vertical = "vertical", e.Horizontal = "horizontal"
})(No || (No = {}));
var ha;
(function (e) {
    e.Partial = "partial", e.Full = "full"
})(ha || (ha = {}));
const Pb = {
    inProgress: !1,
    isValid: null,
    from: null,
    fromHandle: null,
    fromPosition: null,
    fromNode: null,
    to: null,
    toHandle: null,
    toPosition: null,
    toNode: null
};
var kr;
(function (e) {
    e.Bezier = "default", e.Straight = "straight", e.Step = "step", e.SmoothStep = "smoothstep", e.SimpleBezier = "simplebezier"
})(kr || (kr = {}));
var zc;
(function (e) {
    e.Arrow = "arrow", e.ArrowClosed = "arrowclosed"
})(zc || (zc = {}));
var q;
(function (e) {
    e.Left = "left", e.Top = "top", e.Right = "right", e.Bottom = "bottom"
})(q || (q = {}));
const Qv = {[q.Left]: q.Right, [q.Right]: q.Left, [q.Top]: q.Bottom, [q.Bottom]: q.Top};

function Tb(e) {
    return e === null ? null : e ? "valid" : "invalid"
}

const Mb = e => "id" in e && "source" in e && "target" in e,
    U4 = e => "id" in e && "position" in e && !("source" in e) && !("target" in e),
    um = e => "id" in e && "internals" in e && !("source" in e) && !("target" in e), Ia = (e, t = [0, 0]) => {
        const {width: n, height: r} = sr(e), o = e.origin ?? t, s = n * o[0], i = r * o[1];
        return {x: e.position.x - s, y: e.position.y - i}
    }, W4 = (e, t = {nodeOrigin: [0, 0]}) => {
        if (e.length === 0) return {x: 0, y: 0, width: 0, height: 0};
        const n = e.reduce((r, o) => {
            const s = typeof o == "string";
            let i = !t.nodeLookup && !s ? o : void 0;
            t.nodeLookup && (i = s ? t.nodeLookup.get(o) : um(o) ? o : t.nodeLookup.get(o.id));
            const a = i ? Bc(i, t.nodeOrigin) : {x: 0, y: 0, x2: 0, y2: 0};
            return Mu(r, a)
        }, {x: 1 / 0, y: 1 / 0, x2: -1 / 0, y2: -1 / 0});
        return Ru(n)
    }, Oa = (e, t = {}) => {
        if (e.size === 0) return {x: 0, y: 0, width: 0, height: 0};
        let n = {x: 1 / 0, y: 1 / 0, x2: -1 / 0, y2: -1 / 0};
        return e.forEach(r => {
            if (t.filter === void 0 || t.filter(r)) {
                const o = Bc(r);
                n = Mu(n, o)
            }
        }), Ru(n)
    }, Rb = (e, t, [n, r, o] = [0, 0, 1], s = !1, i = !1) => {
        const a = {...Da(t, [n, r, o]), width: t.width / o, height: t.height / o}, l = [];
        for (const u of e.values()) {
            const {measured: d, selectable: f = !0, hidden: p = !1} = u;
            if (i && !f || p) continue;
            const h = d.width ?? u.width ?? u.initialWidth ?? null, x = d.height ?? u.height ?? u.initialHeight ?? null,
                m = ma(a, Us(u)), b = (h ?? 0) * (x ?? 0), y = s && m > 0;
            (!u.internals.handleBounds || y || m >= b || u.dragging) && l.push(u)
        }
        return l
    }, K4 = (e, t) => {
        const n = new Set;
        return e.forEach(r => {
            n.add(r.id)
        }), t.filter(r => n.has(r.source) || n.has(r.target))
    };

function Y4(e, t) {
    const n = new Map, r = t != null && t.nodes ? new Set(t.nodes.map(o => o.id)) : null;
    return e.forEach(o => {
        o.measured.width && o.measured.height && ((t == null ? void 0 : t.includeHiddenNodes) || !o.hidden) && (!r || r.has(o.id)) && n.set(o.id, o)
    }), n
}

async function G4({nodes: e, width: t, height: n, panZoom: r, minZoom: o, maxZoom: s}, i) {
    if (e.size === 0) return Promise.resolve(!0);
    const a = Y4(e, i), l = Oa(a),
        u = dm(l, t, n, (i == null ? void 0 : i.minZoom) ?? o, (i == null ? void 0 : i.maxZoom) ?? s, (i == null ? void 0 : i.padding) ?? .1);
    return await r.setViewport(u, {duration: i == null ? void 0 : i.duration}), Promise.resolve(!0)
}

function Ab({nodeId: e, nextPosition: t, nodeLookup: n, nodeOrigin: r = [0, 0], nodeExtent: o, onError: s}) {
    const i = n.get(e), a = i.parentId ? n.get(i.parentId) : void 0, {
        x: l,
        y: u
    } = a ? a.internals.positionAbsolute : {x: 0, y: 0}, d = i.origin ?? r;
    let f = o;
    if (i.extent === "parent" && !i.expandParent) if (!a) s == null || s("005", In.error005()); else {
        const h = a.measured.width, x = a.measured.height;
        h && x && (f = [[l, u], [l + h, u + x]])
    } else a && Ws(i.extent) && (f = [[i.extent[0][0] + l, i.extent[0][1] + u], [i.extent[1][0] + l, i.extent[1][1] + u]]);
    const p = Ws(f) ? Ao(t, f, i.measured) : t;
    return (i.measured.width === void 0 || i.measured.height === void 0) && (s == null || s("015", In.error015())), {
        position: {
            x: p.x - l + (i.measured.width ?? 0) * d[0],
            y: p.y - u + (i.measured.height ?? 0) * d[1]
        }, positionAbsolute: p
    }
}

async function X4({nodesToRemove: e = [], edgesToRemove: t = [], nodes: n, edges: r, onBeforeDelete: o}) {
    const s = new Set(e.map(p => p.id)), i = [];
    for (const p of n) {
        if (p.deletable === !1) continue;
        const h = s.has(p.id), x = !h && p.parentId && i.find(m => m.id === p.parentId);
        (h || x) && i.push(p)
    }
    const a = new Set(t.map(p => p.id)), l = r.filter(p => p.deletable !== !1), d = K4(i, l);
    for (const p of l) a.has(p.id) && !d.find(x => x.id === p.id) && d.push(p);
    if (!o) return {edges: d, nodes: i};
    const f = await o({nodes: i, edges: d});
    return typeof f == "boolean" ? f ? {edges: d, nodes: i} : {edges: [], nodes: []} : f
}

const Hs = (e, t = 0, n = 1) => Math.min(Math.max(e, t), n), Ao = (e = {x: 0, y: 0}, t, n) => ({
    x: Hs(e.x, t[0][0], t[1][0] - ((n == null ? void 0 : n.width) ?? 0)),
    y: Hs(e.y, t[0][1], t[1][1] - ((n == null ? void 0 : n.height) ?? 0))
});

function Ib(e, t, n) {
    const {width: r, height: o} = sr(n), {x: s, y: i} = n.internals.positionAbsolute;
    return Ao(e, [[s, i], [s + r, i + o]], t)
}

const Zv = (e, t, n) => e < t ? Hs(Math.abs(e - t), 1, t) / t : e > n ? -Hs(Math.abs(e - n), 1, t) / t : 0,
    Ob = (e, t, n = 15, r = 40) => {
        const o = Zv(e.x, r, t.width - r) * n, s = Zv(e.y, r, t.height - r) * n;
        return [o, s]
    },
    Mu = (e, t) => ({x: Math.min(e.x, t.x), y: Math.min(e.y, t.y), x2: Math.max(e.x2, t.x2), y2: Math.max(e.y2, t.y2)}),
    bp = ({x: e, y: t, width: n, height: r}) => ({x: e, y: t, x2: e + n, y2: t + r}),
    Ru = ({x: e, y: t, x2: n, y2: r}) => ({x: e, y: t, width: n - e, height: r - t}), Us = (e, t = [0, 0]) => {
        var o, s;
        const {x: n, y: r} = um(e) ? e.internals.positionAbsolute : Ia(e, t);
        return {
            x: n,
            y: r,
            width: ((o = e.measured) == null ? void 0 : o.width) ?? e.width ?? e.initialWidth ?? 0,
            height: ((s = e.measured) == null ? void 0 : s.height) ?? e.height ?? e.initialHeight ?? 0
        }
    }, Bc = (e, t = [0, 0]) => {
        var o, s;
        const {x: n, y: r} = um(e) ? e.internals.positionAbsolute : Ia(e, t);
        return {
            x: n,
            y: r,
            x2: n + (((o = e.measured) == null ? void 0 : o.width) ?? e.width ?? e.initialWidth ?? 0),
            y2: r + (((s = e.measured) == null ? void 0 : s.height) ?? e.height ?? e.initialHeight ?? 0)
        }
    }, Db = (e, t) => Ru(Mu(bp(e), bp(t))), ma = (e, t) => {
        const n = Math.max(0, Math.min(e.x + e.width, t.x + t.width) - Math.max(e.x, t.x)),
            r = Math.max(0, Math.min(e.y + e.height, t.y + t.height) - Math.max(e.y, t.y));
        return Math.ceil(n * r)
    }, Jv = e => an(e.width) && an(e.height) && an(e.x) && an(e.y), an = e => !isNaN(e) && isFinite(e), q4 = (e, t) => {
    }, Au = (e, t = [1, 1]) => ({x: t[0] * Math.round(e.x / t[0]), y: t[1] * Math.round(e.y / t[1])}),
    Da = ({x: e, y: t}, [n, r, o], s = !1, i = [1, 1]) => {
        const a = {x: (e - n) / o, y: (t - r) / o};
        return s ? Au(a, i) : a
    }, Vc = ({x: e, y: t}, [n, r, o]) => ({x: e * o + n, y: t * o + r});

function Wo(e, t) {
    if (typeof e == "number") return Math.floor((t - t / (1 + e)) * .5);
    if (typeof e == "string" && e.endsWith("px")) {
        const n = parseFloat(e);
        if (!Number.isNaN(n)) return Math.floor(n)
    }
    if (typeof e == "string" && e.endsWith("%")) {
        const n = parseFloat(e);
        if (!Number.isNaN(n)) return Math.floor(t * n * .01)
    }
    return console.error(`[React Flow] The padding value "${e}" is invalid. Please provide a number or a string with a valid unit (px or %).`), 0
}

function Q4(e, t, n) {
    if (typeof e == "string" || typeof e == "number") {
        const r = Wo(e, n), o = Wo(e, t);
        return {top: r, right: o, bottom: r, left: o, x: o * 2, y: r * 2}
    }
    if (typeof e == "object") {
        const r = Wo(e.top ?? e.y ?? 0, n), o = Wo(e.bottom ?? e.y ?? 0, n), s = Wo(e.left ?? e.x ?? 0, t),
            i = Wo(e.right ?? e.x ?? 0, t);
        return {top: r, right: i, bottom: o, left: s, x: s + i, y: r + o}
    }
    return {top: 0, right: 0, bottom: 0, left: 0, x: 0, y: 0}
}

function Z4(e, t, n, r, o, s) {
    const {x: i, y: a} = Vc(e, [t, n, r]), {x: l, y: u} = Vc({x: e.x + e.width, y: e.y + e.height}, [t, n, r]),
        d = o - l, f = s - u;
    return {left: Math.floor(i), top: Math.floor(a), right: Math.floor(d), bottom: Math.floor(f)}
}

const dm = (e, t, n, r, o, s) => {
    const i = Q4(s, t, n), a = (t - i.x) / e.width, l = (n - i.y) / e.height, u = Math.min(a, l), d = Hs(u, r, o),
        f = e.x + e.width / 2, p = e.y + e.height / 2, h = t / 2 - f * d, x = n / 2 - p * d, m = Z4(e, h, x, d, t, n),
        b = {
            left: Math.min(m.left - i.left, 0),
            top: Math.min(m.top - i.top, 0),
            right: Math.min(m.right - i.right, 0),
            bottom: Math.min(m.bottom - i.bottom, 0)
        };
    return {x: h - b.left + b.right, y: x - b.top + b.bottom, zoom: d}
}, Hc = () => {
    var e;
    return typeof navigator < "u" && ((e = navigator == null ? void 0 : navigator.userAgent) == null ? void 0 : e.indexOf("Mac")) >= 0
};

function Ws(e) {
    return e !== void 0 && e !== "parent"
}

function sr(e) {
    var t, n;
    return {
        width: ((t = e.measured) == null ? void 0 : t.width) ?? e.width ?? e.initialWidth ?? 0,
        height: ((n = e.measured) == null ? void 0 : n.height) ?? e.height ?? e.initialHeight ?? 0
    }
}

function $b(e) {
    var t, n;
    return (((t = e.measured) == null ? void 0 : t.width) ?? e.width ?? e.initialWidth) !== void 0 && (((n = e.measured) == null ? void 0 : n.height) ?? e.height ?? e.initialHeight) !== void 0
}

function Lb(e, t = {width: 0, height: 0}, n, r, o) {
    const s = {...e}, i = r.get(n);
    if (i) {
        const a = i.origin || o;
        s.x += i.internals.positionAbsolute.x - (t.width ?? 0) * a[0], s.y += i.internals.positionAbsolute.y - (t.height ?? 0) * a[1]
    }
    return s
}

function e0(e, t) {
    if (e.size !== t.size) return !1;
    for (const n of e) if (!t.has(n)) return !1;
    return !0
}

function J4() {
    let e, t;
    return {
        promise: new Promise((r, o) => {
            e = r, t = o
        }), resolve: e, reject: t
    }
}

function Li(e, {snapGrid: t = [0, 0], snapToGrid: n = !1, transform: r, containerBounds: o}) {
    const {x: s, y: i} = qn(e),
        a = Da({x: s - ((o == null ? void 0 : o.left) ?? 0), y: i - ((o == null ? void 0 : o.top) ?? 0)}, r), {
            x: l,
            y: u
        } = n ? Au(a, t) : a;
    return {xSnapped: l, ySnapped: u, ...a}
}

const fm = e => ({width: e.offsetWidth, height: e.offsetHeight}), Fb = e => {
    var t;
    return ((t = e == null ? void 0 : e.getRootNode) == null ? void 0 : t.call(e)) || (window == null ? void 0 : window.document)
}, e3 = ["INPUT", "SELECT", "TEXTAREA"];

function zb(e) {
    var r, o;
    const t = ((o = (r = e.composedPath) == null ? void 0 : r.call(e)) == null ? void 0 : o[0]) || e.target;
    return (t == null ? void 0 : t.nodeType) !== 1 ? !1 : e3.includes(t.nodeName) || t.hasAttribute("contenteditable") || !!t.closest(".nokey")
}

const Bb = e => "clientX" in e, qn = (e, t) => {
    var s, i;
    const n = Bb(e), r = n ? e.clientX : (s = e.touches) == null ? void 0 : s[0].clientX,
        o = n ? e.clientY : (i = e.touches) == null ? void 0 : i[0].clientY;
    return {x: r - ((t == null ? void 0 : t.left) ?? 0), y: o - ((t == null ? void 0 : t.top) ?? 0)}
}, t0 = (e, t, n, r, o) => {
    const s = t.querySelectorAll(`.${e}`);
    return !s || !s.length ? null : Array.from(s).map(i => {
        const a = i.getBoundingClientRect();
        return {
            id: i.getAttribute("data-handleid"),
            type: e,
            nodeId: o,
            position: i.getAttribute("data-handlepos"),
            x: (a.left - n.left) / r,
            y: (a.top - n.top) / r, ...fm(i)
        }
    })
};

function Vb({
                sourceX: e,
                sourceY: t,
                targetX: n,
                targetY: r,
                sourceControlX: o,
                sourceControlY: s,
                targetControlX: i,
                targetControlY: a
            }) {
    const l = e * .125 + o * .375 + i * .375 + n * .125, u = t * .125 + s * .375 + a * .375 + r * .125,
        d = Math.abs(l - e), f = Math.abs(u - t);
    return [l, u, d, f]
}

function jl(e, t) {
    return e >= 0 ? .5 * e : t * 25 * Math.sqrt(-e)
}

function n0({pos: e, x1: t, y1: n, x2: r, y2: o, c: s}) {
    switch (e) {
        case q.Left:
            return [t - jl(t - r, s), n];
        case q.Right:
            return [t + jl(r - t, s), n];
        case q.Top:
            return [t, n - jl(n - o, s)];
        case q.Bottom:
            return [t, n + jl(o - n, s)]
    }
}

function Hb({
                sourceX: e,
                sourceY: t,
                sourcePosition: n = q.Bottom,
                targetX: r,
                targetY: o,
                targetPosition: s = q.Top,
                curvature: i = .25
            }) {
    const [a, l] = n0({pos: n, x1: e, y1: t, x2: r, y2: o, c: i}), [u, d] = n0({
        pos: s,
        x1: r,
        y1: o,
        x2: e,
        y2: t,
        c: i
    }), [f, p, h, x] = Vb({
        sourceX: e,
        sourceY: t,
        targetX: r,
        targetY: o,
        sourceControlX: a,
        sourceControlY: l,
        targetControlX: u,
        targetControlY: d
    });
    return [`M${e},${t} C${a},${l} ${u},${d} ${r},${o}`, f, p, h, x]
}

function Ub({sourceX: e, sourceY: t, targetX: n, targetY: r}) {
    const o = Math.abs(n - e) / 2, s = n < e ? n + o : n - o, i = Math.abs(r - t) / 2, a = r < t ? r + i : r - i;
    return [s, a, o, i]
}

function t3({sourceNode: e, targetNode: t, selected: n = !1, zIndex: r = 0, elevateOnSelect: o = !1}) {
    if (!o) return r;
    const s = n || t.selected || e.selected, i = Math.max(e.internals.z || 0, t.internals.z || 0, 1e3);
    return r + (s ? i : 0)
}

function n3({sourceNode: e, targetNode: t, width: n, height: r, transform: o}) {
    const s = Mu(Bc(e), Bc(t));
    s.x === s.x2 && (s.x2 += 1), s.y === s.y2 && (s.y2 += 1);
    const i = {x: -o[0] / o[2], y: -o[1] / o[2], width: n / o[2], height: r / o[2]};
    return ma(i, Ru(s)) > 0
}

const r3 = ({source: e, sourceHandle: t, target: n, targetHandle: r}) => `xy-edge__${e}${t || ""}-${n}${r || ""}`,
    o3 = (e, t) => t.some(n => n.source === e.source && n.target === e.target && (n.sourceHandle === e.sourceHandle || !n.sourceHandle && !e.sourceHandle) && (n.targetHandle === e.targetHandle || !n.targetHandle && !e.targetHandle)),
    Wb = (e, t) => {
        if (!e.source || !e.target) return t;
        let n;
        return Mb(e) ? n = {...e} : n = {
            ...e,
            id: r3(e)
        }, o3(n, t) ? t : (n.sourceHandle === null && delete n.sourceHandle, n.targetHandle === null && delete n.targetHandle, t.concat(n))
    };

function Kb({sourceX: e, sourceY: t, targetX: n, targetY: r}) {
    const [o, s, i, a] = Ub({sourceX: e, sourceY: t, targetX: n, targetY: r});
    return [`M ${e},${t}L ${n},${r}`, o, s, i, a]
}

const r0 = {[q.Left]: {x: -1, y: 0}, [q.Right]: {x: 1, y: 0}, [q.Top]: {x: 0, y: -1}, [q.Bottom]: {x: 0, y: 1}},
    s3 = ({source: e, sourcePosition: t = q.Bottom, target: n}) => t === q.Left || t === q.Right ? e.x < n.x ? {
        x: 1,
        y: 0
    } : {x: -1, y: 0} : e.y < n.y ? {x: 0, y: 1} : {x: 0, y: -1},
    o0 = (e, t) => Math.sqrt(Math.pow(t.x - e.x, 2) + Math.pow(t.y - e.y, 2));

function i3({source: e, sourcePosition: t = q.Bottom, target: n, targetPosition: r = q.Top, center: o, offset: s}) {
    const i = r0[t], a = r0[r], l = {x: e.x + i.x * s, y: e.y + i.y * s}, u = {x: n.x + a.x * s, y: n.y + a.y * s},
        d = s3({source: l, sourcePosition: t, target: u}), f = d.x !== 0 ? "x" : "y", p = d[f];
    let h = [], x, m;
    const b = {x: 0, y: 0}, y = {x: 0, y: 0}, [w, v, C, S] = Ub({
        sourceX: e.x,
        sourceY: e.y,
        targetX: n.x,
        targetY: n.y
    });
    if (i[f] * a[f] === -1) {
        x = o.x ?? w, m = o.y ?? v;
        const E = [{x, y: l.y}, {x, y: u.y}], T = [{x: l.x, y: m}, {x: u.x, y: m}];
        i[f] === p ? h = f === "x" ? E : T : h = f === "x" ? T : E
    } else {
        const E = [{x: l.x, y: u.y}], T = [{x: u.x, y: l.y}];
        if (f === "x" ? h = i.x === p ? T : E : h = i.y === p ? E : T, t === r) {
            const B = Math.abs(e[f] - n[f]);
            if (B <= s) {
                const k = Math.min(s - 1, s - B);
                i[f] === p ? b[f] = (l[f] > e[f] ? -1 : 1) * k : y[f] = (u[f] > n[f] ? -1 : 1) * k
            }
        }
        if (t !== r) {
            const B = f === "x" ? "y" : "x", k = i[f] === a[B], M = l[B] > u[B], j = l[B] < u[B];
            (i[f] === 1 && (!k && M || k && j) || i[f] !== 1 && (!k && j || k && M)) && (h = f === "x" ? E : T)
        }
        const O = {x: l.x + b.x, y: l.y + b.y}, D = {x: u.x + y.x, y: u.y + y.y},
            L = Math.max(Math.abs(O.x - h[0].x), Math.abs(D.x - h[0].x)),
            $ = Math.max(Math.abs(O.y - h[0].y), Math.abs(D.y - h[0].y));
        L >= $ ? (x = (O.x + D.x) / 2, m = h[0].y) : (x = h[0].x, m = (O.y + D.y) / 2)
    }
    return [[e, {x: l.x + b.x, y: l.y + b.y}, ...h, {x: u.x + y.x, y: u.y + y.y}, n], x, m, C, S]
}

function a3(e, t, n, r) {
    const o = Math.min(o0(e, t) / 2, o0(t, n) / 2, r), {x: s, y: i} = t;
    if (e.x === s && s === n.x || e.y === i && i === n.y) return `L${s} ${i}`;
    if (e.y === i) {
        const u = e.x < n.x ? -1 : 1, d = e.y < n.y ? 1 : -1;
        return `L ${s + o * u},${i}Q ${s},${i} ${s},${i + o * d}`
    }
    const a = e.x < n.x ? 1 : -1, l = e.y < n.y ? -1 : 1;
    return `L ${s},${i + o * l}Q ${s},${i} ${s + o * a},${i}`
}

function Cp({
                sourceX: e,
                sourceY: t,
                sourcePosition: n = q.Bottom,
                targetX: r,
                targetY: o,
                targetPosition: s = q.Top,
                borderRadius: i = 5,
                centerX: a,
                centerY: l,
                offset: u = 20
            }) {
    const [d, f, p, h, x] = i3({
        source: {x: e, y: t},
        sourcePosition: n,
        target: {x: r, y: o},
        targetPosition: s,
        center: {x: a, y: l},
        offset: u
    });
    return [d.reduce((b, y, w) => {
        let v = "";
        return w > 0 && w < d.length - 1 ? v = a3(d[w - 1], y, d[w + 1], i) : v = `${w === 0 ? "M" : "L"}${y.x} ${y.y}`, b += v, b
    }, ""), f, p, h, x]
}

function s0(e) {
    var t;
    return e && !!(e.internals.handleBounds || (t = e.handles) != null && t.length) && !!(e.measured.width || e.width || e.initialWidth)
}

function l3(e) {
    var f;
    const {sourceNode: t, targetNode: n} = e;
    if (!s0(t) || !s0(n)) return null;
    const r = t.internals.handleBounds || i0(t.handles), o = n.internals.handleBounds || i0(n.handles),
        s = a0((r == null ? void 0 : r.source) ?? [], e.sourceHandle),
        i = a0(e.connectionMode === Vs.Strict ? (o == null ? void 0 : o.target) ?? [] : ((o == null ? void 0 : o.target) ?? []).concat((o == null ? void 0 : o.source) ?? []), e.targetHandle);
    if (!s || !i) return (f = e.onError) == null || f.call(e, "008", In.error008(s ? "target" : "source", {
        id: e.id,
        sourceHandle: e.sourceHandle,
        targetHandle: e.targetHandle
    })), null;
    const a = (s == null ? void 0 : s.position) || q.Bottom, l = (i == null ? void 0 : i.position) || q.Top,
        u = ga(t, s, a), d = ga(n, i, l);
    return {sourceX: u.x, sourceY: u.y, targetX: d.x, targetY: d.y, sourcePosition: a, targetPosition: l}
}

function i0(e) {
    if (!e) return null;
    const t = [], n = [];
    for (const r of e) r.width = r.width ?? 1, r.height = r.height ?? 1, r.type === "source" ? t.push(r) : r.type === "target" && n.push(r);
    return {source: t, target: n}
}

function ga(e, t, n = q.Left, r = !1) {
    const o = ((t == null ? void 0 : t.x) ?? 0) + e.internals.positionAbsolute.x,
        s = ((t == null ? void 0 : t.y) ?? 0) + e.internals.positionAbsolute.y, {width: i, height: a} = t ?? sr(e);
    if (r) return {x: o + i / 2, y: s + a / 2};
    switch ((t == null ? void 0 : t.position) ?? n) {
        case q.Top:
            return {x: o + i / 2, y: s};
        case q.Right:
            return {x: o + i, y: s + a / 2};
        case q.Bottom:
            return {x: o + i / 2, y: s + a};
        case q.Left:
            return {x: o, y: s + a / 2}
    }
}

function a0(e, t) {
    return e && (t ? e.find(n => n.id === t) : e[0]) || null
}

function Sp(e, t) {
    return e ? typeof e == "string" ? e : `${t ? `${t}__` : ""}${Object.keys(e).sort().map(r => `${r}=${e[r]}`).join("&")}` : ""
}

function c3(e, {id: t, defaultColor: n, defaultMarkerStart: r, defaultMarkerEnd: o}) {
    const s = new Set;
    return e.reduce((i, a) => ([a.markerStart || r, a.markerEnd || o].forEach(l => {
        if (l && typeof l == "object") {
            const u = Sp(l, t);
            s.has(u) || (i.push({id: u, color: l.color || n, ...l}), s.add(u))
        }
    }), i), []).sort((i, a) => i.id.localeCompare(a.id))
}

const pm = {nodeOrigin: [0, 0], nodeExtent: pa, elevateNodesOnSelect: !0, defaults: {}},
    u3 = {...pm, checkEquality: !0};

function hm(e, t) {
    const n = {...e};
    for (const r in t) t[r] !== void 0 && (n[r] = t[r]);
    return n
}

function d3(e, t, n) {
    const r = hm(pm, n);
    for (const o of e.values()) if (o.parentId) mm(o, e, t, r); else {
        const s = Ia(o, r.nodeOrigin), i = Ws(o.extent) ? o.extent : r.nodeExtent, a = Ao(s, i, sr(o));
        o.internals.positionAbsolute = a
    }
}

function Np(e, t, n, r) {
    var l, u;
    const o = hm(u3, r);
    let s = e.length > 0;
    const i = new Map(t), a = o != null && o.elevateNodesOnSelect ? 1e3 : 0;
    t.clear(), n.clear();
    for (const d of e) {
        let f = i.get(d.id);
        if (o.checkEquality && d === (f == null ? void 0 : f.internals.userNode)) t.set(d.id, f); else {
            const p = Ia(d, o.nodeOrigin), h = Ws(d.extent) ? d.extent : o.nodeExtent, x = Ao(p, h, sr(d));
            f = {
                ...o.defaults, ...d,
                measured: {
                    width: (l = d.measured) == null ? void 0 : l.width,
                    height: (u = d.measured) == null ? void 0 : u.height
                },
                internals: {
                    positionAbsolute: x,
                    handleBounds: d.measured ? f == null ? void 0 : f.internals.handleBounds : void 0,
                    z: Yb(d, a),
                    userNode: d
                }
            }, t.set(d.id, f)
        }
        (f.measured === void 0 || f.measured.width === void 0 || f.measured.height === void 0) && !f.hidden && (s = !1), d.parentId && mm(f, t, n, r)
    }
    return s
}

function f3(e, t) {
    if (!e.parentId) return;
    const n = t.get(e.parentId);
    n ? n.set(e.id, e) : t.set(e.parentId, new Map([[e.id, e]]))
}

function mm(e, t, n, r) {
    const {elevateNodesOnSelect: o, nodeOrigin: s, nodeExtent: i} = hm(pm, r), a = e.parentId, l = t.get(a);
    if (!l) {
        console.warn(`Parent node ${a} not found. Please make sure that parent nodes are in front of their child nodes in the nodes array.`);
        return
    }
    f3(e, n);
    const u = o ? 1e3 : 0, {x: d, y: f, z: p} = p3(e, l, s, i, u), {positionAbsolute: h} = e.internals,
        x = d !== h.x || f !== h.y;
    (x || p !== e.internals.z) && t.set(e.id, {
        ...e,
        internals: {...e.internals, positionAbsolute: x ? {x: d, y: f} : h, z: p}
    })
}

function Yb(e, t) {
    return (an(e.zIndex) ? e.zIndex : 0) + (e.selected ? t : 0)
}

function p3(e, t, n, r, o) {
    const {x: s, y: i} = t.internals.positionAbsolute, a = sr(e), l = Ia(e, n),
        u = Ws(e.extent) ? Ao(l, e.extent, a) : l;
    let d = Ao({x: s + u.x, y: i + u.y}, r, a);
    e.extent === "parent" && (d = Ib(d, a, t));
    const f = Yb(e, o), p = t.internals.z ?? 0;
    return {x: d.x, y: d.y, z: p > f ? p : f}
}

function gm(e, t, n, r = [0, 0]) {
    var i;
    const o = [], s = new Map;
    for (const a of e) {
        const l = t.get(a.parentId);
        if (!l) continue;
        const u = ((i = s.get(a.parentId)) == null ? void 0 : i.expandedRect) ?? Us(l), d = Db(u, a.rect);
        s.set(a.parentId, {expandedRect: d, parent: l})
    }
    return s.size > 0 && s.forEach(({expandedRect: a, parent: l}, u) => {
        var v;
        const d = l.internals.positionAbsolute, f = sr(l), p = l.origin ?? r,
            h = a.x < d.x ? Math.round(Math.abs(d.x - a.x)) : 0, x = a.y < d.y ? Math.round(Math.abs(d.y - a.y)) : 0,
            m = Math.max(f.width, Math.round(a.width)), b = Math.max(f.height, Math.round(a.height)),
            y = (m - f.width) * p[0], w = (b - f.height) * p[1];
        (h > 0 || x > 0 || y || w) && (o.push({
            id: u,
            type: "position",
            position: {x: l.position.x - h + y, y: l.position.y - x + w}
        }), (v = n.get(u)) == null || v.forEach(C => {
            e.some(S => S.id === C.id) || o.push({
                id: C.id,
                type: "position",
                position: {x: C.position.x + h, y: C.position.y + x}
            })
        })), (f.width < a.width || f.height < a.height || h || x) && o.push({
            id: u,
            type: "dimensions",
            setAttributes: !0,
            dimensions: {width: m + (h ? p[0] * h - y : 0), height: b + (x ? p[1] * x - w : 0)}
        })
    }), o
}

function h3(e, t, n, r, o, s) {
    const i = r == null ? void 0 : r.querySelector(".xyflow__viewport");
    let a = !1;
    if (!i) return {changes: [], updatedInternals: a};
    const l = [], u = window.getComputedStyle(i), {m22: d} = new window.DOMMatrixReadOnly(u.transform), f = [];
    for (const p of e.values()) {
        const h = t.get(p.id);
        if (!h) continue;
        if (h.hidden) {
            t.set(h.id, {...h, internals: {...h.internals, handleBounds: void 0}}), a = !0;
            continue
        }
        const x = fm(p.nodeElement), m = h.measured.width !== x.width || h.measured.height !== x.height;
        if (!!(x.width && x.height && (m || !h.internals.handleBounds || p.force))) {
            const y = p.nodeElement.getBoundingClientRect(), w = Ws(h.extent) ? h.extent : s;
            let {positionAbsolute: v} = h.internals;
            h.parentId && h.extent === "parent" ? v = Ib(v, x, t.get(h.parentId)) : w && (v = Ao(v, w, x));
            const C = {
                ...h,
                measured: x,
                internals: {
                    ...h.internals,
                    positionAbsolute: v,
                    handleBounds: {
                        source: t0("source", p.nodeElement, y, d, h.id),
                        target: t0("target", p.nodeElement, y, d, h.id)
                    }
                }
            };
            t.set(h.id, C), h.parentId && mm(C, t, n, {nodeOrigin: o}), a = !0, m && (l.push({
                id: h.id,
                type: "dimensions",
                dimensions: x
            }), h.expandParent && h.parentId && f.push({id: h.id, parentId: h.parentId, rect: Us(C, o)}))
        }
    }
    if (f.length > 0) {
        const p = gm(f, t, n, o);
        l.push(...p)
    }
    return {changes: l, updatedInternals: a}
}

async function m3({delta: e, panZoom: t, transform: n, translateExtent: r, width: o, height: s}) {
    if (!t || !e.x && !e.y) return Promise.resolve(!1);
    const i = await t.setViewportConstrained({x: n[0] + e.x, y: n[1] + e.y, zoom: n[2]}, [[0, 0], [o, s]], r),
        a = !!i && (i.x !== n[0] || i.y !== n[1] || i.k !== n[2]);
    return Promise.resolve(a)
}

function l0(e, t, n, r, o, s) {
    let i = o;
    const a = r.get(i) || new Map;
    r.set(i, a.set(n, t)), i = `${o}-${e}`;
    const l = r.get(i) || new Map;
    if (r.set(i, l.set(n, t)), s) {
        i = `${o}-${e}-${s}`;
        const u = r.get(i) || new Map;
        r.set(i, u.set(n, t))
    }
}

function Gb(e, t, n) {
    e.clear(), t.clear();
    for (const r of n) {
        const {source: o, target: s, sourceHandle: i = null, targetHandle: a = null} = r,
            l = {edgeId: r.id, source: o, target: s, sourceHandle: i, targetHandle: a}, u = `${o}-${i}--${s}-${a}`,
            d = `${s}-${a}--${o}-${i}`;
        l0("source", l, d, e, o, i), l0("target", l, u, e, s, a), t.set(r.id, r)
    }
}

function Xb(e, t) {
    if (!e.parentId) return !1;
    const n = t.get(e.parentId);
    return n ? n.selected ? !0 : Xb(n, t) : !1
}

function c0(e, t, n) {
    var o;
    let r = e;
    do {
        if ((o = r == null ? void 0 : r.matches) != null && o.call(r, t)) return !0;
        if (r === n) return !1;
        r = r == null ? void 0 : r.parentElement
    } while (r);
    return !1
}

function g3(e, t, n, r) {
    const o = new Map;
    for (const [s, i] of e) if ((i.selected || i.id === r) && (!i.parentId || !Xb(i, e)) && (i.draggable || t && typeof i.draggable > "u")) {
        const a = e.get(s);
        a && o.set(s, {
            id: s,
            position: a.position || {x: 0, y: 0},
            distance: {x: n.x - a.internals.positionAbsolute.x, y: n.y - a.internals.positionAbsolute.y},
            extent: a.extent,
            parentId: a.parentId,
            origin: a.origin,
            expandParent: a.expandParent,
            internals: {positionAbsolute: a.internals.positionAbsolute || {x: 0, y: 0}},
            measured: {width: a.measured.width ?? 0, height: a.measured.height ?? 0}
        })
    }
    return o
}

function Wd({nodeId: e, dragItems: t, nodeLookup: n, dragging: r = !0}) {
    var i, a, l;
    const o = [];
    for (const [u, d] of t) {
        const f = (i = n.get(u)) == null ? void 0 : i.internals.userNode;
        f && o.push({...f, position: d.position, dragging: r})
    }
    if (!e) return [o[0], o];
    const s = (a = n.get(e)) == null ? void 0 : a.internals.userNode;
    return [s ? {...s, position: ((l = t.get(e)) == null ? void 0 : l.position) || s.position, dragging: r} : o[0], o]
}

function v3({onNodeMouseDown: e, getStoreItems: t, onDragStart: n, onDrag: r, onDragStop: o}) {
    let s = {x: null, y: null}, i = 0, a = new Map, l = !1, u = {x: 0, y: 0}, d = null, f = !1, p = null, h = !1;

    function x({
                   noDragClassName: b,
                   handleSelector: y,
                   domNode: w,
                   isSelectable: v,
                   nodeId: C,
                   nodeClickDistance: S = 0
               }) {
        p = jt(w);

        function N({x: D, y: L}, $) {
            const {
                nodeLookup: B,
                nodeExtent: k,
                snapGrid: M,
                snapToGrid: j,
                nodeOrigin: A,
                onNodeDrag: P,
                onSelectionDrag: _,
                onError: R,
                updateNodePositions: z
            } = t();
            s = {x: D, y: L};
            let I = !1, V = {x: 0, y: 0, x2: 0, y2: 0};
            if (a.size > 1 && k) {
                const H = Oa(a);
                V = bp(H)
            }
            for (const [H, U] of a) {
                if (!B.has(H)) continue;
                let K = {x: D - U.distance.x, y: L - U.distance.y};
                j && (K = Au(K, M));
                let F = [[k[0][0], k[0][1]], [k[1][0], k[1][1]]];
                if (a.size > 1 && k && !U.extent) {
                    const {positionAbsolute: te} = U.internals, ee = te.x - V.x + k[0][0],
                        Q = te.x + U.measured.width - V.x2 + k[1][0], ue = te.y - V.y + k[0][1],
                        ie = te.y + U.measured.height - V.y2 + k[1][1];
                    F = [[ee, ue], [Q, ie]]
                }
                const {position: Y, positionAbsolute: X} = Ab({
                    nodeId: H,
                    nextPosition: K,
                    nodeLookup: B,
                    nodeExtent: F,
                    nodeOrigin: A,
                    onError: R
                });
                I = I || U.position.x !== Y.x || U.position.y !== Y.y, U.position = Y, U.internals.positionAbsolute = X
            }
            if (I && (z(a, !0), $ && (r || P || !C && _))) {
                const [H, U] = Wd({nodeId: C, dragItems: a, nodeLookup: B});
                r == null || r($, a, H, U), P == null || P($, H, U), C || _ == null || _($, U)
            }
        }

        async function E() {
            if (!d) return;
            const {transform: D, panBy: L, autoPanSpeed: $, autoPanOnNodeDrag: B} = t();
            if (!B) {
                l = !1, cancelAnimationFrame(i);
                return
            }
            const [k, M] = Ob(u, d, $);
            (k !== 0 || M !== 0) && (s.x = (s.x ?? 0) - k / D[2], s.y = (s.y ?? 0) - M / D[2], await L({
                x: k,
                y: M
            }) && N(s, null)), i = requestAnimationFrame(E)
        }

        function T(D) {
            var I;
            const {
                nodeLookup: L,
                multiSelectionActive: $,
                nodesDraggable: B,
                transform: k,
                snapGrid: M,
                snapToGrid: j,
                selectNodesOnDrag: A,
                onNodeDragStart: P,
                onSelectionDragStart: _,
                unselectNodesAndEdges: R
            } = t();
            f = !0, (!A || !v) && !$ && C && ((I = L.get(C)) != null && I.selected || R()), v && A && C && (e == null || e(C));
            const z = Li(D.sourceEvent, {transform: k, snapGrid: M, snapToGrid: j, containerBounds: d});
            if (s = z, a = g3(L, B, z, C), a.size > 0 && (n || P || !C && _)) {
                const [V, H] = Wd({nodeId: C, dragItems: a, nodeLookup: L});
                n == null || n(D.sourceEvent, a, V, H), P == null || P(D.sourceEvent, V, H), C || _ == null || _(D.sourceEvent, H)
            }
        }

        const O = fb().clickDistance(S).on("start", D => {
            const {domNode: L, nodeDragThreshold: $, transform: B, snapGrid: k, snapToGrid: M} = t();
            d = (L == null ? void 0 : L.getBoundingClientRect()) || null, h = !1, $ === 0 && T(D), s = Li(D.sourceEvent, {
                transform: B,
                snapGrid: k,
                snapToGrid: M,
                containerBounds: d
            }), u = qn(D.sourceEvent, d)
        }).on("drag", D => {
            const {
                autoPanOnNodeDrag: L,
                transform: $,
                snapGrid: B,
                snapToGrid: k,
                nodeDragThreshold: M,
                nodeLookup: j
            } = t(), A = Li(D.sourceEvent, {transform: $, snapGrid: B, snapToGrid: k, containerBounds: d});
            if ((D.sourceEvent.type === "touchmove" && D.sourceEvent.touches.length > 1 || C && !j.has(C)) && (h = !0), !h) {
                if (!l && L && f && (l = !0, E()), !f) {
                    const P = A.xSnapped - (s.x ?? 0), _ = A.ySnapped - (s.y ?? 0);
                    Math.sqrt(P * P + _ * _) > M && T(D)
                }
                (s.x !== A.xSnapped || s.y !== A.ySnapped) && a && f && (u = qn(D.sourceEvent, d), N(A, D.sourceEvent))
            }
        }).on("end", D => {
            if (!(!f || h) && (l = !1, f = !1, cancelAnimationFrame(i), a.size > 0)) {
                const {nodeLookup: L, updateNodePositions: $, onNodeDragStop: B, onSelectionDragStop: k} = t();
                if ($(a, !1), o || B || !C && k) {
                    const [M, j] = Wd({nodeId: C, dragItems: a, nodeLookup: L, dragging: !1});
                    o == null || o(D.sourceEvent, a, M, j), B == null || B(D.sourceEvent, M, j), C || k == null || k(D.sourceEvent, j)
                }
            }
        }).filter(D => {
            const L = D.target;
            return !D.button && (!b || !c0(L, `.${b}`, w)) && (!y || c0(L, y, w))
        });
        p.call(O)
    }

    function m() {
        p == null || p.on(".drag", null)
    }

    return {update: x, destroy: m}
}

function y3(e, t, n) {
    const r = [], o = {x: e.x - n, y: e.y - n, width: n * 2, height: n * 2};
    for (const s of t.values()) ma(o, Us(s)) > 0 && r.push(s);
    return r
}

const x3 = 250;

function w3(e, t, n, r) {
    var a, l;
    let o = [], s = 1 / 0;
    const i = y3(e, n, t + x3);
    for (const u of i) {
        const d = [...((a = u.internals.handleBounds) == null ? void 0 : a.source) ?? [], ...((l = u.internals.handleBounds) == null ? void 0 : l.target) ?? []];
        for (const f of d) {
            if (r.nodeId === f.nodeId && r.type === f.type && r.id === f.id) continue;
            const {x: p, y: h} = ga(u, f, f.position, !0), x = Math.sqrt(Math.pow(p - e.x, 2) + Math.pow(h - e.y, 2));
            x > t || (x < s ? (o = [{...f, x: p, y: h}], s = x) : x === s && o.push({...f, x: p, y: h}))
        }
    }
    if (!o.length) return null;
    if (o.length > 1) {
        const u = r.type === "source" ? "target" : "source";
        return o.find(d => d.type === u) ?? o[0]
    }
    return o[0]
}

function qb(e, t, n, r, o, s = !1) {
    var u, d, f;
    const i = r.get(e);
    if (!i) return null;
    const a = o === "strict" ? (u = i.internals.handleBounds) == null ? void 0 : u[t] : [...((d = i.internals.handleBounds) == null ? void 0 : d.source) ?? [], ...((f = i.internals.handleBounds) == null ? void 0 : f.target) ?? []],
        l = (n ? a == null ? void 0 : a.find(p => p.id === n) : a == null ? void 0 : a[0]) ?? null;
    return l && s ? {...l, ...ga(i, l, l.position, !0)} : l
}

function Qb(e, t) {
    return e || (t != null && t.classList.contains("target") ? "target" : t != null && t.classList.contains("source") ? "source" : null)
}

function b3(e, t) {
    let n = null;
    return t ? n = !0 : e && !t && (n = !1), n
}

const Zb = () => !0;

function C3(e, {
    connectionMode: t,
    connectionRadius: n,
    handleId: r,
    nodeId: o,
    edgeUpdaterType: s,
    isTarget: i,
    domNode: a,
    nodeLookup: l,
    lib: u,
    autoPanOnConnect: d,
    flowId: f,
    panBy: p,
    cancelConnection: h,
    onConnectStart: x,
    onConnect: m,
    onConnectEnd: b,
    isValidConnection: y = Zb,
    onReconnectEnd: w,
    updateConnection: v,
    getTransform: C,
    getFromHandle: S,
    autoPanSpeed: N
}) {
    const E = Fb(e.target);
    let T = 0, O;
    const {x: D, y: L} = qn(e), $ = E == null ? void 0 : E.elementFromPoint(D, L), B = Qb(s, $),
        k = a == null ? void 0 : a.getBoundingClientRect();
    if (!k || !B) return;
    const M = qb(o, B, r, l, t);
    if (!M) return;
    let j = qn(e, k), A = !1, P = null, _ = !1, R = null;

    function z() {
        if (!d || !k) return;
        const [X, te] = Ob(j, k, N);
        p({x: X, y: te}), T = requestAnimationFrame(z)
    }

    const I = {...M, nodeId: o, type: B, position: M.position}, V = l.get(o), U = {
        inProgress: !0,
        isValid: null,
        from: ga(V, I, q.Left, !0),
        fromHandle: I,
        fromPosition: I.position,
        fromNode: V,
        to: j,
        toHandle: null,
        toPosition: Qv[I.position],
        toNode: null
    };
    v(U);
    let K = U;
    x == null || x(e, {nodeId: o, handleId: r, handleType: B});

    function F(X) {
        if (!S() || !I) {
            Y(X);
            return
        }
        const te = C();
        j = qn(X, k), O = w3(Da(j, te, !1, [1, 1]), n, l, I), A || (z(), A = !0);
        const ee = Jb(X, {
            handle: O,
            connectionMode: t,
            fromNodeId: o,
            fromHandleId: r,
            fromType: i ? "target" : "source",
            isValidConnection: y,
            doc: E,
            lib: u,
            flowId: f,
            nodeLookup: l
        });
        R = ee.handleDomNode, P = ee.connection, _ = b3(!!O, ee.isValid);
        const Q = {
            ...K,
            isValid: _,
            to: O && _ ? Vc({x: O.x, y: O.y}, te) : j,
            toHandle: ee.toHandle,
            toPosition: _ && ee.toHandle ? ee.toHandle.position : Qv[I.position],
            toNode: ee.toHandle ? l.get(ee.toHandle.nodeId) : null
        };
        _ && O && K.toHandle && Q.toHandle && K.toHandle.type === Q.toHandle.type && K.toHandle.nodeId === Q.toHandle.nodeId && K.toHandle.id === Q.toHandle.id && K.to.x === Q.to.x && K.to.y === Q.to.y || (v(Q), K = Q)
    }

    function Y(X) {
        (O || R) && P && _ && (m == null || m(P));
        const {inProgress: te, ...ee} = K, Q = {...ee, toPosition: K.toHandle ? K.toPosition : null};
        b == null || b(X, Q), s && (w == null || w(X, Q)), h(), cancelAnimationFrame(T), A = !1, _ = !1, P = null, R = null, E.removeEventListener("mousemove", F), E.removeEventListener("mouseup", Y), E.removeEventListener("touchmove", F), E.removeEventListener("touchend", Y)
    }

    E.addEventListener("mousemove", F), E.addEventListener("mouseup", Y), E.addEventListener("touchmove", F), E.addEventListener("touchend", Y)
}

function Jb(e, {
    handle: t,
    connectionMode: n,
    fromNodeId: r,
    fromHandleId: o,
    fromType: s,
    doc: i,
    lib: a,
    flowId: l,
    isValidConnection: u = Zb,
    nodeLookup: d
}) {
    const f = s === "target",
        p = t ? i.querySelector(`.${a}-flow__handle[data-id="${l}-${t == null ? void 0 : t.nodeId}-${t == null ? void 0 : t.id}-${t == null ? void 0 : t.type}"]`) : null, {
            x: h,
            y: x
        } = qn(e), m = i.elementFromPoint(h, x), b = m != null && m.classList.contains(`${a}-flow__handle`) ? m : p,
        y = {handleDomNode: b, isValid: !1, connection: null, toHandle: null};
    if (b) {
        const w = Qb(void 0, b), v = b.getAttribute("data-nodeid"), C = b.getAttribute("data-handleid"),
            S = b.classList.contains("connectable"), N = b.classList.contains("connectableend");
        if (!v || !w) return y;
        const E = {source: f ? v : r, sourceHandle: f ? C : o, target: f ? r : v, targetHandle: f ? o : C};
        y.connection = E;
        const O = S && N && (n === Vs.Strict ? f && w === "source" || !f && w === "target" : v !== r || C !== o);
        y.isValid = O && u(E), y.toHandle = qb(v, w, C, d, n, !1)
    }
    return y
}

const Ep = {onPointerDown: C3, isValid: Jb};

function S3({domNode: e, panZoom: t, getTransform: n, getViewScale: r}) {
    const o = jt(e);

    function s({
                   translateExtent: a,
                   width: l,
                   height: u,
                   zoomStep: d = 10,
                   pannable: f = !0,
                   zoomable: p = !0,
                   inversePan: h = !1
               }) {
        const x = v => {
            const C = n();
            if (v.sourceEvent.type !== "wheel" || !t) return;
            const S = -v.sourceEvent.deltaY * (v.sourceEvent.deltaMode === 1 ? .05 : v.sourceEvent.deltaMode ? 1 : .002) * d,
                N = C[2] * Math.pow(2, S);
            t.scaleTo(N)
        };
        let m = [0, 0];
        const b = v => {
            (v.sourceEvent.type === "mousedown" || v.sourceEvent.type === "touchstart") && (m = [v.sourceEvent.clientX ?? v.sourceEvent.touches[0].clientX, v.sourceEvent.clientY ?? v.sourceEvent.touches[0].clientY])
        }, y = v => {
            const C = n();
            if (v.sourceEvent.type !== "mousemove" && v.sourceEvent.type !== "touchmove" || !t) return;
            const S = [v.sourceEvent.clientX ?? v.sourceEvent.touches[0].clientX, v.sourceEvent.clientY ?? v.sourceEvent.touches[0].clientY],
                N = [S[0] - m[0], S[1] - m[1]];
            m = S;
            const E = r() * Math.max(C[2], Math.log(C[2])) * (h ? -1 : 1), T = {x: C[0] - N[0] * E, y: C[1] - N[1] * E},
                O = [[0, 0], [l, u]];
            t.setViewportConstrained({x: T.x, y: T.y, zoom: C[2]}, O, a)
        }, w = _b().on("start", b).on("zoom", f ? y : null).on("zoom.wheel", p ? x : null);
        o.call(w, {})
    }

    function i() {
        o.on("zoom", null)
    }

    return {update: s, destroy: i, pointer: rn}
}

const N3 = (e, t) => e.x !== t.x || e.y !== t.y || e.zoom !== t.k, Iu = e => ({x: e.x, y: e.y, zoom: e.k}),
    Kd = ({x: e, y: t, zoom: n}) => Tu.translate(e, t).scale(n), cs = (e, t) => e.target.closest(`.${t}`),
    eC = (e, t) => t === 2 && Array.isArray(e) && e.includes(2), Yd = (e, t = 0, n = () => {
    }) => {
        const r = typeof t == "number" && t > 0;
        return r || n(), r ? e.transition().duration(t).on("end", n) : e
    }, tC = e => {
        const t = e.ctrlKey && Hc() ? 10 : 1;
        return -e.deltaY * (e.deltaMode === 1 ? .05 : e.deltaMode ? 1 : .002) * t
    };

function E3({
                zoomPanValues: e,
                noWheelClassName: t,
                d3Selection: n,
                d3Zoom: r,
                panOnScrollMode: o,
                panOnScrollSpeed: s,
                zoomOnPinch: i,
                onPanZoomStart: a,
                onPanZoom: l,
                onPanZoomEnd: u
            }) {
    return d => {
        if (cs(d, t)) return !1;
        d.preventDefault(), d.stopImmediatePropagation();
        const f = n.property("__zoom").k || 1;
        if (d.ctrlKey && i) {
            const b = rn(d), y = tC(d), w = f * Math.pow(2, y);
            r.scaleTo(n, w, b, d);
            return
        }
        const p = d.deltaMode === 1 ? 20 : 1;
        let h = o === No.Vertical ? 0 : d.deltaX * p, x = o === No.Horizontal ? 0 : d.deltaY * p;
        !Hc() && d.shiftKey && o !== No.Vertical && (h = d.deltaY * p, x = 0), r.translateBy(n, -(h / f) * s, -(x / f) * s, {internal: !0});
        const m = Iu(n.property("__zoom"));
        clearTimeout(e.panScrollTimeout), e.isPanScrolling || (e.isPanScrolling = !0, a == null || a(d, m)), e.isPanScrolling && (l == null || l(d, m), e.panScrollTimeout = setTimeout(() => {
            u == null || u(d, m), e.isPanScrolling = !1
        }, 150))
    }
}

function k3({noWheelClassName: e, preventScrolling: t, d3ZoomHandler: n}) {
    return function (r, o) {
        const s = r.type === "wheel", i = !t && s && !r.ctrlKey, a = cs(r, e);
        if (r.ctrlKey && s && a && r.preventDefault(), i || a) return null;
        r.preventDefault(), n.call(this, r, o)
    }
}

function _3({zoomPanValues: e, onDraggingChange: t, onPanZoomStart: n}) {
    return r => {
        var s, i, a;
        if ((s = r.sourceEvent) != null && s.internal) return;
        const o = Iu(r.transform);
        e.mouseButton = ((i = r.sourceEvent) == null ? void 0 : i.button) || 0, e.isZoomingOrPanning = !0, e.prevViewport = o, ((a = r.sourceEvent) == null ? void 0 : a.type) === "mousedown" && t(!0), n && (n == null || n(r.sourceEvent, o))
    }
}

function j3({zoomPanValues: e, panOnDrag: t, onPaneContextMenu: n, onTransformChange: r, onPanZoom: o}) {
    return s => {
        var i, a;
        e.usedRightMouseButton = !!(n && eC(t, e.mouseButton ?? 0)), (i = s.sourceEvent) != null && i.sync || r([s.transform.x, s.transform.y, s.transform.k]), o && !((a = s.sourceEvent) != null && a.internal) && (o == null || o(s.sourceEvent, Iu(s.transform)))
    }
}

function P3({
                zoomPanValues: e,
                panOnDrag: t,
                panOnScroll: n,
                onDraggingChange: r,
                onPanZoomEnd: o,
                onPaneContextMenu: s
            }) {
    return i => {
        var a;
        if (!((a = i.sourceEvent) != null && a.internal) && (e.isZoomingOrPanning = !1, s && eC(t, e.mouseButton ?? 0) && !e.usedRightMouseButton && i.sourceEvent && s(i.sourceEvent), e.usedRightMouseButton = !1, r(!1), o && N3(e.prevViewport, i.transform))) {
            const l = Iu(i.transform);
            e.prevViewport = l, clearTimeout(e.timerId), e.timerId = setTimeout(() => {
                o == null || o(i.sourceEvent, l)
            }, n ? 150 : 0)
        }
    }
}

function T3({
                zoomActivationKeyPressed: e,
                zoomOnScroll: t,
                zoomOnPinch: n,
                panOnDrag: r,
                panOnScroll: o,
                zoomOnDoubleClick: s,
                userSelectionActive: i,
                noWheelClassName: a,
                noPanClassName: l,
                lib: u
            }) {
    return d => {
        var x;
        const f = e || t, p = n && d.ctrlKey;
        if (d.button === 1 && d.type === "mousedown" && (cs(d, `${u}-flow__node`) || cs(d, `${u}-flow__edge`))) return !0;
        if (!r && !f && !o && !s && !n || i || cs(d, a) && d.type === "wheel" || cs(d, l) && (d.type !== "wheel" || o && d.type === "wheel" && !e) || !n && d.ctrlKey && d.type === "wheel") return !1;
        if (!n && d.type === "touchstart" && ((x = d.touches) == null ? void 0 : x.length) > 1) return d.preventDefault(), !1;
        if (!f && !o && !p && d.type === "wheel" || !r && (d.type === "mousedown" || d.type === "touchstart") || Array.isArray(r) && !r.includes(d.button) && d.type === "mousedown") return !1;
        const h = Array.isArray(r) && r.includes(d.button) || !d.button || d.button <= 1;
        return (!d.ctrlKey || d.type === "wheel") && h
    }
}

function M3({
                domNode: e,
                minZoom: t,
                maxZoom: n,
                paneClickDistance: r,
                translateExtent: o,
                viewport: s,
                onPanZoom: i,
                onPanZoomStart: a,
                onPanZoomEnd: l,
                onDraggingChange: u
            }) {
    const d = {
            isZoomingOrPanning: !1,
            usedRightMouseButton: !1,
            prevViewport: {x: 0, y: 0, zoom: 0},
            mouseButton: 0,
            timerId: void 0,
            panScrollTimeout: void 0,
            isPanScrolling: !1
        }, f = e.getBoundingClientRect(),
        p = _b().clickDistance(!an(r) || r < 0 ? 0 : r).scaleExtent([t, n]).translateExtent(o), h = jt(e).call(p);
    v({x: s.x, y: s.y, zoom: Hs(s.zoom, t, n)}, [[0, 0], [f.width, f.height]], o);
    const x = h.on("wheel.zoom"), m = h.on("dblclick.zoom");
    p.wheelDelta(tC);

    function b($, B) {
        return h ? new Promise(k => {
            p == null || p.transform(Yd(h, B == null ? void 0 : B.duration, () => k(!0)), $)
        }) : Promise.resolve(!1)
    }

    function y({
                   noWheelClassName: $,
                   noPanClassName: B,
                   onPaneContextMenu: k,
                   userSelectionActive: M,
                   panOnScroll: j,
                   panOnDrag: A,
                   panOnScrollMode: P,
                   panOnScrollSpeed: _,
                   preventScrolling: R,
                   zoomOnPinch: z,
                   zoomOnScroll: I,
                   zoomOnDoubleClick: V,
                   zoomActivationKeyPressed: H,
                   lib: U,
                   onTransformChange: K
               }) {
        M && !d.isZoomingOrPanning && w();
        const Y = j && !H && !M ? E3({
            zoomPanValues: d,
            noWheelClassName: $,
            d3Selection: h,
            d3Zoom: p,
            panOnScrollMode: P,
            panOnScrollSpeed: _,
            zoomOnPinch: z,
            onPanZoomStart: a,
            onPanZoom: i,
            onPanZoomEnd: l
        }) : k3({noWheelClassName: $, preventScrolling: R, d3ZoomHandler: x});
        if (h.on("wheel.zoom", Y, {passive: !1}), !M) {
            const te = _3({zoomPanValues: d, onDraggingChange: u, onPanZoomStart: a});
            p.on("start", te);
            const ee = j3({zoomPanValues: d, panOnDrag: A, onPaneContextMenu: !!k, onPanZoom: i, onTransformChange: K});
            p.on("zoom", ee);
            const Q = P3({
                zoomPanValues: d,
                panOnDrag: A,
                panOnScroll: j,
                onPaneContextMenu: k,
                onPanZoomEnd: l,
                onDraggingChange: u
            });
            p.on("end", Q)
        }
        const X = T3({
            zoomActivationKeyPressed: H,
            panOnDrag: A,
            zoomOnScroll: I,
            panOnScroll: j,
            zoomOnDoubleClick: V,
            zoomOnPinch: z,
            userSelectionActive: M,
            noPanClassName: B,
            noWheelClassName: $,
            lib: U
        });
        p.filter(X), V ? h.on("dblclick.zoom", m) : h.on("dblclick.zoom", null)
    }

    function w() {
        p.on("zoom", null)
    }

    async function v($, B, k) {
        const M = Kd($), j = p == null ? void 0 : p.constrain()(M, B, k);
        return j && await b(j), new Promise(A => A(j))
    }

    async function C($, B) {
        const k = Kd($);
        return await b(k, B), new Promise(M => M(k))
    }

    function S($) {
        if (h) {
            const B = Kd($), k = h.property("__zoom");
            (k.k !== $.zoom || k.x !== $.x || k.y !== $.y) && (p == null || p.transform(h, B, null, {sync: !0}))
        }
    }

    function N() {
        const $ = h ? kb(h.node()) : {x: 0, y: 0, k: 1};
        return {x: $.x, y: $.y, zoom: $.k}
    }

    function E($, B) {
        return h ? new Promise(k => {
            p == null || p.scaleTo(Yd(h, B == null ? void 0 : B.duration, () => k(!0)), $)
        }) : Promise.resolve(!1)
    }

    function T($, B) {
        return h ? new Promise(k => {
            p == null || p.scaleBy(Yd(h, B == null ? void 0 : B.duration, () => k(!0)), $)
        }) : Promise.resolve(!1)
    }

    function O($) {
        p == null || p.scaleExtent($)
    }

    function D($) {
        p == null || p.translateExtent($)
    }

    function L($) {
        const B = !an($) || $ < 0 ? 0 : $;
        p == null || p.clickDistance(B)
    }

    return {
        update: y,
        destroy: w,
        setViewport: C,
        setViewportConstrained: v,
        getViewport: N,
        scaleTo: E,
        scaleBy: T,
        setScaleExtent: O,
        setTranslateExtent: D,
        syncViewport: S,
        setClickDistance: L
    }
}

var Fi;
(function (e) {
    e.Line = "line", e.Handle = "handle"
})(Fi || (Fi = {}));

function R3({width: e, prevWidth: t, height: n, prevHeight: r, affectsX: o, affectsY: s}) {
    const i = e - t, a = n - r, l = [i > 0 ? 1 : i < 0 ? -1 : 0, a > 0 ? 1 : a < 0 ? -1 : 0];
    return i && o && (l[0] = l[0] * -1), a && s && (l[1] = l[1] * -1), l
}

function A3(e) {
    const t = e.includes("right") || e.includes("left"), n = e.includes("bottom") || e.includes("top"),
        r = e.includes("left"), o = e.includes("top");
    return {isHorizontal: t, isVertical: n, affectsX: r, affectsY: o}
}

function vr(e, t) {
    return Math.max(0, t - e)
}

function yr(e, t) {
    return Math.max(0, e - t)
}

function Pl(e, t, n) {
    return Math.max(0, t - e, e - n)
}

function u0(e, t) {
    return e ? !t : t
}

function I3(e, t, n, r, o, s, i, a) {
    let {affectsX: l, affectsY: u} = t;
    const {isHorizontal: d, isVertical: f} = t, p = d && f, {xSnapped: h, ySnapped: x} = n, {
        minWidth: m,
        maxWidth: b,
        minHeight: y,
        maxHeight: w
    } = r, {x: v, y: C, width: S, height: N, aspectRatio: E} = e;
    let T = Math.floor(d ? h - e.pointerX : 0), O = Math.floor(f ? x - e.pointerY : 0);
    const D = S + (l ? -T : T), L = N + (u ? -O : O), $ = -s[0] * S, B = -s[1] * N;
    let k = Pl(D, m, b), M = Pl(L, y, w);
    if (i) {
        let P = 0, _ = 0;
        l && T < 0 ? P = vr(v + T + $, i[0][0]) : !l && T > 0 && (P = yr(v + D + $, i[1][0])), u && O < 0 ? _ = vr(C + O + B, i[0][1]) : !u && O > 0 && (_ = yr(C + L + B, i[1][1])), k = Math.max(k, P), M = Math.max(M, _)
    }
    if (a) {
        let P = 0, _ = 0;
        l && T > 0 ? P = yr(v + T, a[0][0]) : !l && T < 0 && (P = vr(v + D, a[1][0])), u && O > 0 ? _ = yr(C + O, a[0][1]) : !u && O < 0 && (_ = vr(C + L, a[1][1])), k = Math.max(k, P), M = Math.max(M, _)
    }
    if (o) {
        if (d) {
            const P = Pl(D / E, y, w) * E;
            if (k = Math.max(k, P), i) {
                let _ = 0;
                !l && !u || l && !u && p ? _ = yr(C + B + D / E, i[1][1]) * E : _ = vr(C + B + (l ? T : -T) / E, i[0][1]) * E, k = Math.max(k, _)
            }
            if (a) {
                let _ = 0;
                !l && !u || l && !u && p ? _ = vr(C + D / E, a[1][1]) * E : _ = yr(C + (l ? T : -T) / E, a[0][1]) * E, k = Math.max(k, _)
            }
        }
        if (f) {
            const P = Pl(L * E, m, b) / E;
            if (M = Math.max(M, P), i) {
                let _ = 0;
                !l && !u || u && !l && p ? _ = yr(v + L * E + $, i[1][0]) / E : _ = vr(v + (u ? O : -O) * E + $, i[0][0]) / E, M = Math.max(M, _)
            }
            if (a) {
                let _ = 0;
                !l && !u || u && !l && p ? _ = vr(v + L * E, a[1][0]) / E : _ = yr(v + (u ? O : -O) * E, a[0][0]) / E, M = Math.max(M, _)
            }
        }
    }
    O = O + (O < 0 ? M : -M), T = T + (T < 0 ? k : -k), o && (p ? D > L * E ? O = (u0(l, u) ? -T : T) / E : T = (u0(l, u) ? -O : O) * E : d ? (O = T / E, u = l) : (T = O * E, l = u));
    const j = l ? v + T : v, A = u ? C + O : C;
    return {
        width: S + (l ? -T : T),
        height: N + (u ? -O : O),
        x: s[0] * T * (l ? -1 : 1) + j,
        y: s[1] * O * (u ? -1 : 1) + A
    }
}

const nC = {width: 0, height: 0, x: 0, y: 0}, O3 = {...nC, pointerX: 0, pointerY: 0, aspectRatio: 1};

function D3(e) {
    return [[0, 0], [e.measured.width, e.measured.height]]
}

function $3(e, t, n) {
    const r = t.position.x + e.position.x, o = t.position.y + e.position.y, s = e.measured.width ?? 0,
        i = e.measured.height ?? 0, a = n[0] * s, l = n[1] * i;
    return [[r - a, o - l], [r + s - a, o + i - l]]
}

function L3({domNode: e, nodeId: t, getStoreItems: n, onChange: r, onEnd: o}) {
    const s = jt(e);

    function i({
                   controlPosition: l,
                   boundaries: u,
                   keepAspectRatio: d,
                   resizeDirection: f,
                   onResizeStart: p,
                   onResize: h,
                   onResizeEnd: x,
                   shouldResize: m
               }) {
        let b = {...nC}, y = {...O3};
        const w = A3(l);
        let v, C = null, S = [], N, E, T;
        const O = fb().on("start", D => {
            const {nodeLookup: L, transform: $, snapGrid: B, snapToGrid: k, nodeOrigin: M, paneDomNode: j} = n();
            if (v = L.get(t), !v) return;
            C = (j == null ? void 0 : j.getBoundingClientRect()) ?? null;
            const {xSnapped: A, ySnapped: P} = Li(D.sourceEvent, {
                transform: $,
                snapGrid: B,
                snapToGrid: k,
                containerBounds: C
            });
            b = {
                width: v.measured.width ?? 0,
                height: v.measured.height ?? 0,
                x: v.position.x ?? 0,
                y: v.position.y ?? 0
            }, y = {
                ...b,
                pointerX: A,
                pointerY: P,
                aspectRatio: b.width / b.height
            }, N = void 0, v.parentId && (v.extent === "parent" || v.expandParent) && (N = L.get(v.parentId), E = N && v.extent === "parent" ? D3(N) : void 0), S = [], T = void 0;
            for (const [_, R] of L) if (R.parentId === t && (S.push({
                id: _,
                position: {...R.position},
                extent: R.extent
            }), R.extent === "parent" || R.expandParent)) {
                const z = $3(R, v, R.origin ?? M);
                T ? T = [[Math.min(z[0][0], T[0][0]), Math.min(z[0][1], T[0][1])], [Math.max(z[1][0], T[1][0]), Math.max(z[1][1], T[1][1])]] : T = z
            }
            p == null || p(D, {...b})
        }).on("drag", D => {
            const {transform: L, snapGrid: $, snapToGrid: B, nodeOrigin: k} = n(),
                M = Li(D.sourceEvent, {transform: L, snapGrid: $, snapToGrid: B, containerBounds: C}), j = [];
            if (!v) return;
            const {x: A, y: P, width: _, height: R} = b, z = {}, I = v.origin ?? k, {
                width: V,
                height: H,
                x: U,
                y: K
            } = I3(y, w, M, u, d, I, E, T), F = V !== _, Y = H !== R, X = U !== A && F, te = K !== P && Y;
            if (!X && !te && !F && !Y) return;
            if ((X || te || I[0] === 1 || I[1] === 1) && (z.x = X ? U : b.x, z.y = te ? K : b.y, b.x = z.x, b.y = z.y, S.length > 0)) {
                const ie = U - A, we = K - P;
                for (const Ne of S) Ne.position = {
                    x: Ne.position.x - ie + I[0] * (V - _),
                    y: Ne.position.y - we + I[1] * (H - R)
                }, j.push(Ne)
            }
            if ((F || Y) && (z.width = F && (!f || f === "horizontal") ? V : b.width, z.height = Y && (!f || f === "vertical") ? H : b.height, b.width = z.width, b.height = z.height), N && v.expandParent) {
                const ie = I[0] * (z.width ?? 0);
                z.x && z.x < ie && (b.x = ie, y.x = y.x - (z.x - ie));
                const we = I[1] * (z.height ?? 0);
                z.y && z.y < we && (b.y = we, y.y = y.y - (z.y - we))
            }
            const ee = R3({
                width: b.width,
                prevWidth: _,
                height: b.height,
                prevHeight: R,
                affectsX: w.affectsX,
                affectsY: w.affectsY
            }), Q = {...b, direction: ee};
            (m == null ? void 0 : m(D, Q)) !== !1 && (h == null || h(D, Q), r(z, j))
        }).on("end", D => {
            x == null || x(D, {...b}), o == null || o({...b})
        });
        s.call(O)
    }

    function a() {
        s.on(".drag", null)
    }

    return {update: i, destroy: a}
}

var rC = {exports: {}}, oC = {}, sC = {exports: {}}, iC = {};
/**
 * @license React
 * use-sync-external-store-shim.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var Ks = g;

function F3(e, t) {
    return e === t && (e !== 0 || 1 / e === 1 / t) || e !== e && t !== t
}

var z3 = typeof Object.is == "function" ? Object.is : F3, B3 = Ks.useState, V3 = Ks.useEffect, H3 = Ks.useLayoutEffect,
    U3 = Ks.useDebugValue;

function W3(e, t) {
    var n = t(), r = B3({inst: {value: n, getSnapshot: t}}), o = r[0].inst, s = r[1];
    return H3(function () {
        o.value = n, o.getSnapshot = t, Gd(o) && s({inst: o})
    }, [e, n, t]), V3(function () {
        return Gd(o) && s({inst: o}), e(function () {
            Gd(o) && s({inst: o})
        })
    }, [e]), U3(n), n
}

function Gd(e) {
    var t = e.getSnapshot;
    e = e.value;
    try {
        var n = t();
        return !z3(e, n)
    } catch {
        return !0
    }
}

function K3(e, t) {
    return t()
}

var Y3 = typeof window > "u" || typeof window.document > "u" || typeof window.document.createElement > "u" ? K3 : W3;
iC.useSyncExternalStore = Ks.useSyncExternalStore !== void 0 ? Ks.useSyncExternalStore : Y3;
sC.exports = iC;
var G3 = sC.exports;
/**
 * @license React
 * use-sync-external-store-shim/with-selector.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var Ou = g, X3 = G3;

function q3(e, t) {
    return e === t && (e !== 0 || 1 / e === 1 / t) || e !== e && t !== t
}

var Q3 = typeof Object.is == "function" ? Object.is : q3, Z3 = X3.useSyncExternalStore, J3 = Ou.useRef,
    e5 = Ou.useEffect, t5 = Ou.useMemo, n5 = Ou.useDebugValue;
oC.useSyncExternalStoreWithSelector = function (e, t, n, r, o) {
    var s = J3(null);
    if (s.current === null) {
        var i = {hasValue: !1, value: null};
        s.current = i
    } else i = s.current;
    s = t5(function () {
        function l(h) {
            if (!u) {
                if (u = !0, d = h, h = r(h), o !== void 0 && i.hasValue) {
                    var x = i.value;
                    if (o(x, h)) return f = x
                }
                return f = h
            }
            if (x = f, Q3(d, h)) return x;
            var m = r(h);
            return o !== void 0 && o(x, m) ? (d = h, x) : (d = h, f = m)
        }

        var u = !1, d, f, p = n === void 0 ? null : n;
        return [function () {
            return l(t())
        }, p === null ? void 0 : function () {
            return l(p())
        }]
    }, [t, n, r, o]);
    var a = Z3(e, s[0], s[1]);
    return e5(function () {
        i.hasValue = !0, i.value = a
    }, [a]), n5(a), a
};
rC.exports = oC;
var r5 = rC.exports;
const o5 = zp(r5), s5 = {}, d0 = e => {
    let t;
    const n = new Set, r = (d, f) => {
        const p = typeof d == "function" ? d(t) : d;
        if (!Object.is(p, t)) {
            const h = t;
            t = f ?? (typeof p != "object" || p === null) ? p : Object.assign({}, t, p), n.forEach(x => x(t, h))
        }
    }, o = () => t, l = {
        setState: r,
        getState: o,
        getInitialState: () => u,
        subscribe: d => (n.add(d), () => n.delete(d)),
        destroy: () => {
            (s5 ? "production" : void 0) !== "production" && console.warn("[DEPRECATED] The `destroy` method will be unsupported in a future version. Instead use unsubscribe function returned by subscribe. Everything will be garbage-collected if store is garbage-collected."), n.clear()
        }
    }, u = t = e(r, o, l);
    return l
}, i5 = e => e ? d0(e) : d0, {useDebugValue: a5} = Z, {useSyncExternalStoreWithSelector: l5} = o5, c5 = e => e;

function aC(e, t = c5, n) {
    const r = l5(e.subscribe, e.getState, e.getServerState || e.getInitialState, t, n);
    return a5(r), r
}

const f0 = (e, t) => {
    const n = i5(e), r = (o, s = t) => aC(n, o, s);
    return Object.assign(r, n), r
}, u5 = (e, t) => e ? f0(e, t) : f0;

function $e(e, t) {
    if (Object.is(e, t)) return !0;
    if (typeof e != "object" || e === null || typeof t != "object" || t === null) return !1;
    if (e instanceof Map && t instanceof Map) {
        if (e.size !== t.size) return !1;
        for (const [r, o] of e) if (!Object.is(o, t.get(r))) return !1;
        return !0
    }
    if (e instanceof Set && t instanceof Set) {
        if (e.size !== t.size) return !1;
        for (const r of e) if (!t.has(r)) return !1;
        return !0
    }
    const n = Object.keys(e);
    if (n.length !== Object.keys(t).length) return !1;
    for (const r of n) if (!Object.prototype.hasOwnProperty.call(t, r) || !Object.is(e[r], t[r])) return !1;
    return !0
}

const Du = g.createContext(null), d5 = Du.Provider, lC = In.error001();

function me(e, t) {
    const n = g.useContext(Du);
    if (n === null) throw new Error(lC);
    return aC(n, e, t)
}

function Te() {
    const e = g.useContext(Du);
    if (e === null) throw new Error(lC);
    return g.useMemo(() => ({getState: e.getState, setState: e.setState, subscribe: e.subscribe}), [e])
}

const p0 = {display: "none"}, f5 = {
        position: "absolute",
        width: 1,
        height: 1,
        margin: -1,
        border: 0,
        padding: 0,
        overflow: "hidden",
        clip: "rect(0px, 0px, 0px, 0px)",
        clipPath: "inset(100%)"
    }, cC = "react-flow__node-desc", uC = "react-flow__edge-desc", p5 = "react-flow__aria-live",
    h5 = e => e.ariaLiveMessage;

function m5({rfId: e}) {
    const t = me(h5);
    return c.jsx("div", {id: `${p5}-${e}`, "aria-live": "assertive", "aria-atomic": "true", style: f5, children: t})
}

function g5({rfId: e, disableKeyboardA11y: t}) {
    return c.jsxs(c.Fragment, {
        children: [c.jsxs("div", {
            id: `${cC}-${e}`,
            style: p0,
            children: ["Press enter or space to select a node.", !t && "You can then use the arrow keys to move the node around.", " Press delete to remove it and escape to cancel.", " "]
        }), c.jsx("div", {
            id: `${uC}-${e}`,
            style: p0,
            children: "Press enter or space to select an edge. You can then press delete to remove it or escape to cancel."
        }), !t && c.jsx(m5, {rfId: e})]
    })
}

const v5 = e => e.userSelectionActive ? "none" : "all",
    $u = g.forwardRef(({position: e = "top-left", children: t, className: n, style: r, ...o}, s) => {
        const i = me(v5), a = `${e}`.split("-");
        return c.jsx("div", {
            className: ze(["react-flow__panel", n, ...a]),
            style: {...r, pointerEvents: i},
            ref: s, ...o,
            children: t
        })
    });
$u.displayName = "Panel";

function y5({proOptions: e, position: t = "bottom-right"}) {
    return e != null && e.hideAttribution ? null : c.jsx($u, {
        position: t,
        className: "react-flow__attribution",
        "data-message": "Please only hide this attribution when you are subscribed to React Flow Pro: https://pro.reactflow.dev",
        children: c.jsx("a", {
            href: "https://reactflow.dev",
            target: "_blank",
            rel: "noopener noreferrer",
            "aria-label": "React Flow attribution",
            children: "React Flow"
        })
    })
}

const x5 = e => {
    const t = [], n = [];
    for (const [, r] of e.nodeLookup) r.selected && t.push(r.internals.userNode);
    for (const [, r] of e.edgeLookup) r.selected && n.push(r);
    return {selectedNodes: t, selectedEdges: n}
}, Tl = e => e.id;

function w5(e, t) {
    return $e(e.selectedNodes.map(Tl), t.selectedNodes.map(Tl)) && $e(e.selectedEdges.map(Tl), t.selectedEdges.map(Tl))
}

function b5({onSelectionChange: e}) {
    const t = Te(), {selectedNodes: n, selectedEdges: r} = me(x5, w5);
    return g.useEffect(() => {
        const o = {nodes: n, edges: r};
        e == null || e(o), t.getState().onSelectionChangeHandlers.forEach(s => s(o))
    }, [n, r, e]), null
}

const C5 = e => !!e.onSelectionChangeHandlers;

function S5({onSelectionChange: e}) {
    const t = me(C5);
    return e || t ? c.jsx(b5, {onSelectionChange: e}) : null
}

const dC = [0, 0], N5 = {x: 0, y: 0, zoom: 1},
    E5 = ["nodes", "edges", "defaultNodes", "defaultEdges", "onConnect", "onConnectStart", "onConnectEnd", "onClickConnectStart", "onClickConnectEnd", "nodesDraggable", "nodesConnectable", "nodesFocusable", "edgesFocusable", "edgesReconnectable", "elevateNodesOnSelect", "elevateEdgesOnSelect", "minZoom", "maxZoom", "nodeExtent", "onNodesChange", "onEdgesChange", "elementsSelectable", "connectionMode", "snapGrid", "snapToGrid", "translateExtent", "connectOnClick", "defaultEdgeOptions", "fitView", "fitViewOptions", "onNodesDelete", "onEdgesDelete", "onDelete", "onNodeDrag", "onNodeDragStart", "onNodeDragStop", "onSelectionDrag", "onSelectionDragStart", "onSelectionDragStop", "onMoveStart", "onMove", "onMoveEnd", "noPanClassName", "nodeOrigin", "autoPanOnConnect", "autoPanOnNodeDrag", "onError", "connectionRadius", "isValidConnection", "selectNodesOnDrag", "nodeDragThreshold", "onBeforeDelete", "debug", "autoPanSpeed", "paneClickDistance"],
    h0 = [...E5, "rfId"], k5 = e => ({
        setNodes: e.setNodes,
        setEdges: e.setEdges,
        setMinZoom: e.setMinZoom,
        setMaxZoom: e.setMaxZoom,
        setTranslateExtent: e.setTranslateExtent,
        setNodeExtent: e.setNodeExtent,
        reset: e.reset,
        setDefaultNodesAndEdges: e.setDefaultNodesAndEdges,
        setPaneClickDistance: e.setPaneClickDistance
    }), m0 = {
        translateExtent: pa,
        nodeOrigin: dC,
        minZoom: .5,
        maxZoom: 2,
        elementsSelectable: !0,
        noPanClassName: "nopan",
        rfId: "1",
        paneClickDistance: 0
    };

function _5(e) {
    const {
        setNodes: t,
        setEdges: n,
        setMinZoom: r,
        setMaxZoom: o,
        setTranslateExtent: s,
        setNodeExtent: i,
        reset: a,
        setDefaultNodesAndEdges: l,
        setPaneClickDistance: u
    } = me(k5, $e), d = Te();
    g.useEffect(() => (l(e.defaultNodes, e.defaultEdges), () => {
        f.current = m0, a()
    }), []);
    const f = g.useRef(m0);
    return g.useEffect(() => {
        for (const p of h0) {
            const h = e[p], x = f.current[p];
            h !== x && (typeof e[p] > "u" || (p === "nodes" ? t(h) : p === "edges" ? n(h) : p === "minZoom" ? r(h) : p === "maxZoom" ? o(h) : p === "translateExtent" ? s(h) : p === "nodeExtent" ? i(h) : p === "paneClickDistance" ? u(h) : p === "fitView" ? d.setState({fitViewQueued: h}) : p === "fitViewOptions" ? d.setState({fitViewOptions: h}) : d.setState({[p]: h})))
        }
        f.current = e
    }, h0.map(p => e[p])), null
}

function g0() {
    return typeof window > "u" || !window.matchMedia ? null : window.matchMedia("(prefers-color-scheme: dark)")
}

function j5(e) {
    var r;
    const [t, n] = g.useState(e === "system" ? null : e);
    return g.useEffect(() => {
        if (e !== "system") {
            n(e);
            return
        }
        const o = g0(), s = () => n(o != null && o.matches ? "dark" : "light");
        return s(), o == null || o.addEventListener("change", s), () => {
            o == null || o.removeEventListener("change", s)
        }
    }, [e]), t !== null ? t : (r = g0()) != null && r.matches ? "dark" : "light"
}

const v0 = typeof document < "u" ? document : null;

function va(e = null, t = {target: v0, actInsideInputWithModifier: !0}) {
    const [n, r] = g.useState(!1), o = g.useRef(!1), s = g.useRef(new Set([])), [i, a] = g.useMemo(() => {
        if (e !== null) {
            const u = (Array.isArray(e) ? e : [e]).filter(f => typeof f == "string").map(f => f.replace("+", `
`).replace(`

`, `
+`).split(`
`)), d = u.reduce((f, p) => f.concat(...p), []);
            return [u, d]
        }
        return [[], []]
    }, [e]);
    return g.useEffect(() => {
        const l = (t == null ? void 0 : t.target) || v0;
        if (e !== null) {
            const u = p => {
                var m, b;
                if (o.current = p.ctrlKey || p.metaKey || p.shiftKey || p.altKey, (!o.current || o.current && !t.actInsideInputWithModifier) && zb(p)) return !1;
                const x = x0(p.code, a);
                if (s.current.add(p[x]), y0(i, s.current, !1)) {
                    const y = ((b = (m = p.composedPath) == null ? void 0 : m.call(p)) == null ? void 0 : b[0]) || p.target,
                        w = (y == null ? void 0 : y.nodeName) === "BUTTON" || (y == null ? void 0 : y.nodeName) === "A";
                    t.preventDefault !== !1 && (o.current || !w) && p.preventDefault(), r(!0)
                }
            }, d = p => {
                const h = x0(p.code, a);
                y0(i, s.current, !0) ? (r(!1), s.current.clear()) : s.current.delete(p[h]), p.key === "Meta" && s.current.clear(), o.current = !1
            }, f = () => {
                s.current.clear(), r(!1)
            };
            return l == null || l.addEventListener("keydown", u), l == null || l.addEventListener("keyup", d), window.addEventListener("blur", f), window.addEventListener("contextmenu", f), () => {
                l == null || l.removeEventListener("keydown", u), l == null || l.removeEventListener("keyup", d), window.removeEventListener("blur", f), window.removeEventListener("contextmenu", f)
            }
        }
    }, [e, r]), n
}

function y0(e, t, n) {
    return e.filter(r => n || r.length === t.size).some(r => r.every(o => t.has(o)))
}

function x0(e, t) {
    return t.includes(e) ? "code" : "key"
}

const P5 = () => {
    const e = Te();
    return g.useMemo(() => ({
        zoomIn: t => {
            const {panZoom: n} = e.getState();
            return n ? n.scaleBy(1.2, {duration: t == null ? void 0 : t.duration}) : Promise.resolve(!1)
        }, zoomOut: t => {
            const {panZoom: n} = e.getState();
            return n ? n.scaleBy(1 / 1.2, {duration: t == null ? void 0 : t.duration}) : Promise.resolve(!1)
        }, zoomTo: (t, n) => {
            const {panZoom: r} = e.getState();
            return r ? r.scaleTo(t, {duration: n == null ? void 0 : n.duration}) : Promise.resolve(!1)
        }, getZoom: () => e.getState().transform[2], setViewport: async (t, n) => {
            const {transform: [r, o, s], panZoom: i} = e.getState();
            return i ? (await i.setViewport({
                x: t.x ?? r,
                y: t.y ?? o,
                zoom: t.zoom ?? s
            }, {duration: n == null ? void 0 : n.duration}), Promise.resolve(!0)) : Promise.resolve(!1)
        }, getViewport: () => {
            const [t, n, r] = e.getState().transform;
            return {x: t, y: n, zoom: r}
        }, setCenter: async (t, n, r) => {
            const {width: o, height: s, maxZoom: i, panZoom: a} = e.getState(),
                l = typeof (r == null ? void 0 : r.zoom) < "u" ? r.zoom : i, u = o / 2 - t * l, d = s / 2 - n * l;
            return a ? (await a.setViewport({
                x: u,
                y: d,
                zoom: l
            }, {duration: r == null ? void 0 : r.duration}), Promise.resolve(!0)) : Promise.resolve(!1)
        }, fitBounds: async (t, n) => {
            const {width: r, height: o, minZoom: s, maxZoom: i, panZoom: a} = e.getState(),
                l = dm(t, r, o, s, i, (n == null ? void 0 : n.padding) ?? .1);
            return a ? (await a.setViewport(l, {duration: n == null ? void 0 : n.duration}), Promise.resolve(!0)) : Promise.resolve(!1)
        }, screenToFlowPosition: (t, n = {}) => {
            const {transform: r, snapGrid: o, snapToGrid: s, domNode: i} = e.getState();
            if (!i) return t;
            const {x: a, y: l} = i.getBoundingClientRect(), u = {x: t.x - a, y: t.y - l}, d = n.snapGrid ?? o,
                f = n.snapToGrid ?? s;
            return Da(u, r, f, d)
        }, flowToScreenPosition: t => {
            const {transform: n, domNode: r} = e.getState();
            if (!r) return t;
            const {x: o, y: s} = r.getBoundingClientRect(), i = Vc(t, n);
            return {x: i.x + o, y: i.y + s}
        }
    }), [])
};

function fC(e, t) {
    const n = [], r = new Map, o = [];
    for (const s of e) if (s.type === "add") {
        o.push(s);
        continue
    } else if (s.type === "remove" || s.type === "replace") r.set(s.id, [s]); else {
        const i = r.get(s.id);
        i ? i.push(s) : r.set(s.id, [s])
    }
    for (const s of t) {
        const i = r.get(s.id);
        if (!i) {
            n.push(s);
            continue
        }
        if (i[0].type === "remove") continue;
        if (i[0].type === "replace") {
            n.push({...i[0].item});
            continue
        }
        const a = {...s};
        for (const l of i) T5(l, a);
        n.push(a)
    }
    return o.length && o.forEach(s => {
        s.index !== void 0 ? n.splice(s.index, 0, {...s.item}) : n.push({...s.item})
    }), n
}

function T5(e, t) {
    switch (e.type) {
        case"select": {
            t.selected = e.selected;
            break
        }
        case"position": {
            typeof e.position < "u" && (t.position = e.position), typeof e.dragging < "u" && (t.dragging = e.dragging);
            break
        }
        case"dimensions": {
            typeof e.dimensions < "u" && (t.measured ?? (t.measured = {}), t.measured.width = e.dimensions.width, t.measured.height = e.dimensions.height, e.setAttributes && ((e.setAttributes === !0 || e.setAttributes === "width") && (t.width = e.dimensions.width), (e.setAttributes === !0 || e.setAttributes === "height") && (t.height = e.dimensions.height))), typeof e.resizing == "boolean" && (t.resizing = e.resizing);
            break
        }
    }
}

function pC(e, t) {
    return fC(e, t)
}

function hC(e, t) {
    return fC(e, t)
}

function lo(e, t) {
    return {id: e, type: "select", selected: t}
}

function us(e, t = new Set, n = !1) {
    const r = [];
    for (const [o, s] of e) {
        const i = t.has(o);
        !(s.selected === void 0 && !i) && s.selected !== i && (n && (s.selected = i), r.push(lo(s.id, i)))
    }
    return r
}

function w0({items: e = [], lookup: t}) {
    var o;
    const n = [], r = new Map(e.map(s => [s.id, s]));
    for (const [s, i] of e.entries()) {
        const a = t.get(i.id), l = ((o = a == null ? void 0 : a.internals) == null ? void 0 : o.userNode) ?? a;
        l !== void 0 && l !== i && n.push({id: i.id, item: i, type: "replace"}), l === void 0 && n.push({
            item: i,
            type: "add",
            index: s
        })
    }
    for (const [s] of t) r.get(s) === void 0 && n.push({id: s, type: "remove"});
    return n
}

function b0(e) {
    return {id: e.id, type: "remove"}
}

const C0 = e => U4(e), M5 = e => Mb(e);

function mC(e) {
    return g.forwardRef(e)
}

const R5 = typeof window < "u" ? g.useLayoutEffect : g.useEffect;

function S0(e) {
    const [t, n] = g.useState(BigInt(0)), [r] = g.useState(() => A5(() => n(o => o + BigInt(1))));
    return R5(() => {
        const o = r.get();
        o.length && (e(o), r.reset())
    }, [t]), r
}

function A5(e) {
    let t = [];
    return {
        get: () => t, reset: () => {
            t = []
        }, push: n => {
            t.push(n), e()
        }
    }
}

const gC = g.createContext(null);

function I5({children: e}) {
    const t = Te(), n = g.useCallback(a => {
        const {
            nodes: l = [],
            setNodes: u,
            hasDefaultNodes: d,
            onNodesChange: f,
            nodeLookup: p,
            fitViewQueued: h
        } = t.getState();
        let x = l;
        for (const m of a) x = typeof m == "function" ? m(x) : m;
        if (d) u(x); else {
            const m = w0({items: x, lookup: p});
            m.length > 0 ? f == null || f(m) : h && window.requestAnimationFrame(() => {
                const {fitViewQueued: b, nodes: y, setNodes: w} = t.getState();
                b && w(y)
            })
        }
    }, []), r = S0(n), o = g.useCallback(a => {
        const {edges: l = [], setEdges: u, hasDefaultEdges: d, onEdgesChange: f, edgeLookup: p} = t.getState();
        let h = l;
        for (const x of a) h = typeof x == "function" ? x(h) : x;
        d ? u(h) : f && f(w0({items: h, lookup: p}))
    }, []), s = S0(o), i = g.useMemo(() => ({nodeQueue: r, edgeQueue: s}), []);
    return c.jsx(gC.Provider, {value: i, children: e})
}

function O5() {
    const e = g.useContext(gC);
    if (!e) throw new Error("useBatchContext must be used within a BatchProvider");
    return e
}

const D5 = e => !!e.panZoom;

function $t() {
    const e = P5(), t = Te(), n = O5(), r = me(D5), o = g.useMemo(() => {
        const s = f => t.getState().nodeLookup.get(f), i = f => {
            n.nodeQueue.push(f)
        }, a = f => {
            n.edgeQueue.push(f)
        }, l = f => {
            var y, w;
            const {nodeLookup: p, nodeOrigin: h} = t.getState(), x = C0(f) ? f : p.get(f.id),
                m = x.parentId ? Lb(x.position, x.measured, x.parentId, p, h) : x.position, b = {
                    ...x,
                    position: m,
                    width: ((y = x.measured) == null ? void 0 : y.width) ?? x.width,
                    height: ((w = x.measured) == null ? void 0 : w.height) ?? x.height
                };
            return Us(b)
        }, u = (f, p, h = {replace: !1}) => {
            i(x => x.map(m => {
                if (m.id === f) {
                    const b = typeof p == "function" ? p(m) : p;
                    return h.replace && C0(b) ? b : {...m, ...b}
                }
                return m
            }))
        }, d = (f, p, h = {replace: !1}) => {
            a(x => x.map(m => {
                if (m.id === f) {
                    const b = typeof p == "function" ? p(m) : p;
                    return h.replace && M5(b) ? b : {...m, ...b}
                }
                return m
            }))
        };
        return {
            getNodes: () => t.getState().nodes.map(f => ({...f})), getNode: f => {
                var p;
                return (p = s(f)) == null ? void 0 : p.internals.userNode
            }, getInternalNode: s, getEdges: () => {
                const {edges: f = []} = t.getState();
                return f.map(p => ({...p}))
            }, getEdge: f => t.getState().edgeLookup.get(f), setNodes: i, setEdges: a, addNodes: f => {
                const p = Array.isArray(f) ? f : [f];
                n.nodeQueue.push(h => [...h, ...p])
            }, addEdges: f => {
                const p = Array.isArray(f) ? f : [f];
                n.edgeQueue.push(h => [...h, ...p])
            }, toObject: () => {
                const {nodes: f = [], edges: p = [], transform: h} = t.getState(), [x, m, b] = h;
                return {nodes: f.map(y => ({...y})), edges: p.map(y => ({...y})), viewport: {x, y: m, zoom: b}}
            }, deleteElements: async ({nodes: f = [], edges: p = []}) => {
                const {
                    nodes: h,
                    edges: x,
                    onNodesDelete: m,
                    onEdgesDelete: b,
                    triggerNodeChanges: y,
                    triggerEdgeChanges: w,
                    onDelete: v,
                    onBeforeDelete: C
                } = t.getState(), {nodes: S, edges: N} = await X4({
                    nodesToRemove: f,
                    edgesToRemove: p,
                    nodes: h,
                    edges: x,
                    onBeforeDelete: C
                }), E = N.length > 0, T = S.length > 0;
                if (E) {
                    const O = N.map(b0);
                    b == null || b(N), w(O)
                }
                if (T) {
                    const O = S.map(b0);
                    m == null || m(S), y(O)
                }
                return (T || E) && (v == null || v({nodes: S, edges: N})), {deletedNodes: S, deletedEdges: N}
            }, getIntersectingNodes: (f, p = !0, h) => {
                const x = Jv(f), m = x ? f : l(f), b = h !== void 0;
                return m ? (h || t.getState().nodes).filter(y => {
                    const w = t.getState().nodeLookup.get(y.id);
                    if (w && !x && (y.id === f.id || !w.internals.positionAbsolute)) return !1;
                    const v = Us(b ? y : w), C = ma(v, m);
                    return p && C > 0 || C >= m.width * m.height
                }) : []
            }, isNodeIntersecting: (f, p, h = !0) => {
                const m = Jv(f) ? f : l(f);
                if (!m) return !1;
                const b = ma(m, p);
                return h && b > 0 || b >= m.width * m.height
            }, updateNode: u, updateNodeData: (f, p, h = {replace: !1}) => {
                u(f, x => {
                    const m = typeof p == "function" ? p(x) : p;
                    return h.replace ? {...x, data: m} : {...x, data: {...x.data, ...m}}
                }, h)
            }, updateEdge: d, updateEdgeData: (f, p, h = {replace: !1}) => {
                d(f, x => {
                    const m = typeof p == "function" ? p(x) : p;
                    return h.replace ? {...x, data: m} : {...x, data: {...x.data, ...m}}
                }, h)
            }, getNodesBounds: f => {
                const {nodeLookup: p, nodeOrigin: h} = t.getState();
                return W4(f, {nodeLookup: p, nodeOrigin: h})
            }, getHandleConnections: ({type: f, id: p, nodeId: h}) => {
                var x;
                return Array.from(((x = t.getState().connectionLookup.get(`${h}-${f}${p ? `-${p}` : ""}`)) == null ? void 0 : x.values()) ?? [])
            }, getNodeConnections: ({type: f, handleId: p, nodeId: h}) => {
                var x;
                return Array.from(((x = t.getState().connectionLookup.get(`${h}${f ? p ? `-${f}-${p}` : `-${f}` : ""}`)) == null ? void 0 : x.values()) ?? [])
            }, fitView: async f => {
                const p = t.getState().fitViewResolver ?? J4();
                return t.setState({
                    fitViewQueued: !0,
                    fitViewOptions: f,
                    fitViewResolver: p
                }), n.nodeQueue.push(h => [...h]), p.promise
            }
        }
    }, []);
    return g.useMemo(() => ({...o, ...e, viewportInitialized: r}), [r])
}

const N0 = e => e.selected, $5 = {actInsideInputWithModifier: !1}, L5 = typeof window < "u" ? window : void 0;

function F5({deleteKeyCode: e, multiSelectionKeyCode: t}) {
    const n = Te(), {deleteElements: r} = $t(), o = va(e, $5), s = va(t, {target: L5});
    g.useEffect(() => {
        if (o) {
            const {edges: i, nodes: a} = n.getState();
            r({nodes: a.filter(N0), edges: i.filter(N0)}), n.setState({nodesSelectionActive: !1})
        }
    }, [o]), g.useEffect(() => {
        n.setState({multiSelectionActive: s})
    }, [s])
}

function z5(e) {
    const t = Te();
    g.useEffect(() => {
        const n = () => {
            var o, s;
            if (!e.current) return !1;
            const r = fm(e.current);
            (r.height === 0 || r.width === 0) && ((s = (o = t.getState()).onError) == null || s.call(o, "004", In.error004())), t.setState({
                width: r.width || 500,
                height: r.height || 500
            })
        };
        if (e.current) {
            n(), window.addEventListener("resize", n);
            const r = new ResizeObserver(() => n());
            return r.observe(e.current), () => {
                window.removeEventListener("resize", n), r && e.current && r.unobserve(e.current)
            }
        }
    }, [])
}

const Lu = {position: "absolute", width: "100%", height: "100%", top: 0, left: 0},
    B5 = e => ({userSelectionActive: e.userSelectionActive, lib: e.lib});

function V5({
                onPaneContextMenu: e,
                zoomOnScroll: t = !0,
                zoomOnPinch: n = !0,
                panOnScroll: r = !1,
                panOnScrollSpeed: o = .5,
                panOnScrollMode: s = No.Free,
                zoomOnDoubleClick: i = !0,
                panOnDrag: a = !0,
                defaultViewport: l,
                translateExtent: u,
                minZoom: d,
                maxZoom: f,
                zoomActivationKeyCode: p,
                preventScrolling: h = !0,
                children: x,
                noWheelClassName: m,
                noPanClassName: b,
                onViewportChange: y,
                isControlledViewport: w,
                paneClickDistance: v
            }) {
    const C = Te(), S = g.useRef(null), {userSelectionActive: N, lib: E} = me(B5, $e), T = va(p), O = g.useRef();
    z5(S);
    const D = g.useCallback(L => {
        y == null || y({x: L[0], y: L[1], zoom: L[2]}), w || C.setState({transform: L})
    }, [y, w]);
    return g.useEffect(() => {
        if (S.current) {
            O.current = M3({
                domNode: S.current,
                minZoom: d,
                maxZoom: f,
                translateExtent: u,
                viewport: l,
                paneClickDistance: v,
                onDraggingChange: k => C.setState({paneDragging: k}),
                onPanZoomStart: (k, M) => {
                    const {onViewportChangeStart: j, onMoveStart: A} = C.getState();
                    A == null || A(k, M), j == null || j(M)
                },
                onPanZoom: (k, M) => {
                    const {onViewportChange: j, onMove: A} = C.getState();
                    A == null || A(k, M), j == null || j(M)
                },
                onPanZoomEnd: (k, M) => {
                    const {onViewportChangeEnd: j, onMoveEnd: A} = C.getState();
                    A == null || A(k, M), j == null || j(M)
                }
            });
            const {x: L, y: $, zoom: B} = O.current.getViewport();
            return C.setState({
                panZoom: O.current,
                transform: [L, $, B],
                domNode: S.current.closest(".react-flow")
            }), () => {
                var k;
                (k = O.current) == null || k.destroy()
            }
        }
    }, []), g.useEffect(() => {
        var L;
        (L = O.current) == null || L.update({
            onPaneContextMenu: e,
            zoomOnScroll: t,
            zoomOnPinch: n,
            panOnScroll: r,
            panOnScrollSpeed: o,
            panOnScrollMode: s,
            zoomOnDoubleClick: i,
            panOnDrag: a,
            zoomActivationKeyPressed: T,
            preventScrolling: h,
            noPanClassName: b,
            userSelectionActive: N,
            noWheelClassName: m,
            lib: E,
            onTransformChange: D
        })
    }, [e, t, n, r, o, s, i, a, T, h, b, N, m, E, D]), c.jsx("div", {
        className: "react-flow__renderer",
        ref: S,
        style: Lu,
        children: x
    })
}

const H5 = e => ({userSelectionActive: e.userSelectionActive, userSelectionRect: e.userSelectionRect});

function U5() {
    const {userSelectionActive: e, userSelectionRect: t} = me(H5, $e);
    return e && t ? c.jsx("div", {
        className: "react-flow__selection react-flow__container",
        style: {width: t.width, height: t.height, transform: `translate(${t.x}px, ${t.y}px)`}
    }) : null
}

const Xd = (e, t) => n => {
    n.target === t.current && (e == null || e(n))
}, W5 = e => ({
    userSelectionActive: e.userSelectionActive,
    elementsSelectable: e.elementsSelectable,
    connectionInProgress: e.connection.inProgress,
    dragging: e.paneDragging
});

function K5({
                isSelecting: e,
                selectionKeyPressed: t,
                selectionMode: n = ha.Full,
                panOnDrag: r,
                selectionOnDrag: o,
                onSelectionStart: s,
                onSelectionEnd: i,
                onPaneClick: a,
                onPaneContextMenu: l,
                onPaneScroll: u,
                onPaneMouseEnter: d,
                onPaneMouseMove: f,
                onPaneMouseLeave: p,
                children: h
            }) {
    const x = Te(), {userSelectionActive: m, elementsSelectable: b, dragging: y, connectionInProgress: w} = me(W5, $e),
        v = b && (e || m), C = g.useRef(null), S = g.useRef(), N = g.useRef(new Set), E = g.useRef(new Set),
        T = g.useRef(!1), O = g.useRef(!1), D = A => {
            if (T.current || w) {
                T.current = !1;
                return
            }
            a == null || a(A), x.getState().resetSelectedElements(), x.setState({nodesSelectionActive: !1})
        }, L = A => {
            if (Array.isArray(r) && (r != null && r.includes(2))) {
                A.preventDefault();
                return
            }
            l == null || l(A)
        }, $ = u ? A => u(A) : void 0, B = A => {
            var I, V;
            const {resetSelectedElements: P, domNode: _} = x.getState();
            if (S.current = _ == null ? void 0 : _.getBoundingClientRect(), !b || !e || A.button !== 0 || A.target !== C.current || !S.current) return;
            (V = (I = A.target) == null ? void 0 : I.setPointerCapture) == null || V.call(I, A.pointerId), O.current = !0, T.current = !1;
            const {x: R, y: z} = qn(A.nativeEvent, S.current);
            P(), x.setState({userSelectionRect: {width: 0, height: 0, startX: R, startY: z, x: R, y: z}}), s == null || s(A)
        }, k = A => {
            const {
                userSelectionRect: P,
                transform: _,
                nodeLookup: R,
                edgeLookup: z,
                connectionLookup: I,
                triggerNodeChanges: V,
                triggerEdgeChanges: H,
                defaultEdgeOptions: U
            } = x.getState();
            if (!S.current || !P) return;
            T.current = !0;
            const {x: K, y: F} = qn(A.nativeEvent, S.current), {startX: Y, startY: X} = P, te = {
                startX: Y,
                startY: X,
                x: K < Y ? K : Y,
                y: F < X ? F : X,
                width: Math.abs(K - Y),
                height: Math.abs(F - X)
            }, ee = N.current, Q = E.current;
            N.current = new Set(Rb(R, te, _, n === ha.Partial, !0).map(ie => ie.id)), E.current = new Set;
            const ue = (U == null ? void 0 : U.selectable) ?? !0;
            for (const ie of N.current) {
                const we = I.get(ie);
                if (we) for (const {edgeId: Ne} of we.values()) {
                    const pt = z.get(Ne);
                    pt && (pt.selectable ?? ue) && E.current.add(Ne)
                }
            }
            if (!e0(ee, N.current)) {
                const ie = us(R, N.current, !0);
                V(ie)
            }
            if (!e0(Q, E.current)) {
                const ie = us(z, E.current);
                H(ie)
            }
            x.setState({userSelectionRect: te, userSelectionActive: !0, nodesSelectionActive: !1})
        }, M = A => {
            var _, R;
            if (A.button !== 0 || !O.current) return;
            (R = (_ = A.target) == null ? void 0 : _.releasePointerCapture) == null || R.call(_, A.pointerId);
            const {userSelectionRect: P} = x.getState();
            !m && P && A.target === C.current && (D == null || D(A)), x.setState({
                userSelectionActive: !1,
                userSelectionRect: null,
                nodesSelectionActive: N.current.size > 0
            }), i == null || i(A), (t || o) && (T.current = !1), O.current = !1
        }, j = r === !0 || Array.isArray(r) && r.includes(0);
    return c.jsxs("div", {
        className: ze(["react-flow__pane", {draggable: j, dragging: y, selection: e}]),
        onClick: v ? void 0 : Xd(D, C),
        onContextMenu: Xd(L, C),
        onWheel: Xd($, C),
        onPointerEnter: v ? void 0 : d,
        onPointerDown: v ? B : f,
        onPointerMove: v ? k : f,
        onPointerUp: v ? M : void 0,
        onPointerLeave: p,
        ref: C,
        style: Lu,
        children: [h, c.jsx(U5, {})]
    })
}

function kp({id: e, store: t, unselect: n = !1, nodeRef: r}) {
    const {
        addSelectedNodes: o,
        unselectNodesAndEdges: s,
        multiSelectionActive: i,
        nodeLookup: a,
        onError: l
    } = t.getState(), u = a.get(e);
    if (!u) {
        l == null || l("012", In.error012(e));
        return
    }
    t.setState({nodesSelectionActive: !1}), u.selected ? (n || u.selected && i) && (s({
        nodes: [u],
        edges: []
    }), requestAnimationFrame(() => {
        var d;
        return (d = r == null ? void 0 : r.current) == null ? void 0 : d.blur()
    })) : o([e])
}

function vC({
                nodeRef: e,
                disabled: t = !1,
                noDragClassName: n,
                handleSelector: r,
                nodeId: o,
                isSelectable: s,
                nodeClickDistance: i
            }) {
    const a = Te(), [l, u] = g.useState(!1), d = g.useRef();
    return g.useEffect(() => {
        d.current = v3({
            getStoreItems: () => a.getState(), onNodeMouseDown: f => {
                kp({id: f, store: a, nodeRef: e})
            }, onDragStart: () => {
                u(!0)
            }, onDragStop: () => {
                u(!1)
            }
        })
    }, []), g.useEffect(() => {
        var f, p;
        if (t) (f = d.current) == null || f.destroy(); else if (e.current) return (p = d.current) == null || p.update({
            noDragClassName: n,
            handleSelector: r,
            domNode: e.current,
            isSelectable: s,
            nodeId: o,
            nodeClickDistance: i
        }), () => {
            var h;
            (h = d.current) == null || h.destroy()
        }
    }, [n, r, t, s, e, o]), l
}

const Y5 = e => t => t.selected && (t.draggable || e && typeof t.draggable > "u");

function yC() {
    const e = Te();
    return g.useCallback(n => {
        const {
                nodeExtent: r,
                snapToGrid: o,
                snapGrid: s,
                nodesDraggable: i,
                onError: a,
                updateNodePositions: l,
                nodeLookup: u,
                nodeOrigin: d
            } = e.getState(), f = new Map, p = Y5(i), h = o ? s[0] : 5, x = o ? s[1] : 5, m = n.direction.x * h * n.factor,
            b = n.direction.y * x * n.factor;
        for (const [, y] of u) {
            if (!p(y)) continue;
            let w = {x: y.internals.positionAbsolute.x + m, y: y.internals.positionAbsolute.y + b};
            o && (w = Au(w, s));
            const {position: v, positionAbsolute: C} = Ab({
                nodeId: y.id,
                nextPosition: w,
                nodeLookup: u,
                nodeExtent: r,
                nodeOrigin: d,
                onError: a
            });
            y.position = v, y.internals.positionAbsolute = C, f.set(y.id, y)
        }
        l(f)
    }, [])
}

const vm = g.createContext(null), G5 = vm.Provider;
vm.Consumer;
const xC = () => g.useContext(vm),
    X5 = e => ({connectOnClick: e.connectOnClick, noPanClassName: e.noPanClassName, rfId: e.rfId}),
    q5 = (e, t, n) => r => {
        const {connectionClickStartHandle: o, connectionMode: s, connection: i} = r, {
                fromHandle: a,
                toHandle: l,
                isValid: u
            } = i,
            d = (l == null ? void 0 : l.nodeId) === e && (l == null ? void 0 : l.id) === t && (l == null ? void 0 : l.type) === n;
        return {
            connectingFrom: (a == null ? void 0 : a.nodeId) === e && (a == null ? void 0 : a.id) === t && (a == null ? void 0 : a.type) === n,
            connectingTo: d,
            clickConnecting: (o == null ? void 0 : o.nodeId) === e && (o == null ? void 0 : o.id) === t && (o == null ? void 0 : o.type) === n,
            isPossibleEndHandle: s === Vs.Strict ? (a == null ? void 0 : a.type) !== n : e !== (a == null ? void 0 : a.nodeId) || t !== (a == null ? void 0 : a.id),
            connectionInProcess: !!a,
            clickConnectionInProcess: !!o,
            valid: d && u
        }
    };

function Q5({
                type: e = "source",
                position: t = q.Top,
                isValidConnection: n,
                isConnectable: r = !0,
                isConnectableStart: o = !0,
                isConnectableEnd: s = !0,
                id: i,
                onConnect: a,
                children: l,
                className: u,
                onMouseDown: d,
                onTouchStart: f,
                ...p
            }, h) {
    var M, j;
    const x = i || null, m = e === "target", b = Te(), y = xC(), {
        connectOnClick: w,
        noPanClassName: v,
        rfId: C
    } = me(X5, $e), {
        connectingFrom: S,
        connectingTo: N,
        clickConnecting: E,
        isPossibleEndHandle: T,
        connectionInProcess: O,
        clickConnectionInProcess: D,
        valid: L
    } = me(q5(y, x, e), $e);
    y || (j = (M = b.getState()).onError) == null || j.call(M, "010", In.error010());
    const $ = A => {
        const {defaultEdgeOptions: P, onConnect: _, hasDefaultEdges: R} = b.getState(), z = {...P, ...A};
        if (R) {
            const {edges: I, setEdges: V} = b.getState();
            V(Wb(z, I))
        }
        _ == null || _(z), a == null || a(z)
    }, B = A => {
        if (!y) return;
        const P = Bb(A.nativeEvent);
        if (o && (P && A.button === 0 || !P)) {
            const _ = b.getState();
            Ep.onPointerDown(A.nativeEvent, {
                autoPanOnConnect: _.autoPanOnConnect,
                connectionMode: _.connectionMode,
                connectionRadius: _.connectionRadius,
                domNode: _.domNode,
                nodeLookup: _.nodeLookup,
                lib: _.lib,
                isTarget: m,
                handleId: x,
                nodeId: y,
                flowId: _.rfId,
                panBy: _.panBy,
                cancelConnection: _.cancelConnection,
                onConnectStart: _.onConnectStart,
                onConnectEnd: _.onConnectEnd,
                updateConnection: _.updateConnection,
                onConnect: $,
                isValidConnection: n || _.isValidConnection,
                getTransform: () => b.getState().transform,
                getFromHandle: () => b.getState().connection.fromHandle,
                autoPanSpeed: _.autoPanSpeed
            })
        }
        P ? d == null || d(A) : f == null || f(A)
    }, k = A => {
        const {
            onClickConnectStart: P,
            onClickConnectEnd: _,
            connectionClickStartHandle: R,
            connectionMode: z,
            isValidConnection: I,
            lib: V,
            rfId: H,
            nodeLookup: U,
            connection: K
        } = b.getState();
        if (!y || !R && !o) return;
        if (!R) {
            P == null || P(A.nativeEvent, {
                nodeId: y,
                handleId: x,
                handleType: e
            }), b.setState({connectionClickStartHandle: {nodeId: y, type: e, id: x}});
            return
        }
        const F = Fb(A.target), Y = n || I, {
            connection: X,
            isValid: te
        } = Ep.isValid(A.nativeEvent, {
            handle: {nodeId: y, id: x, type: e},
            connectionMode: z,
            fromNodeId: R.nodeId,
            fromHandleId: R.id || null,
            fromType: R.type,
            isValidConnection: Y,
            flowId: H,
            doc: F,
            lib: V,
            nodeLookup: U
        });
        te && X && $(X);
        const ee = structuredClone(K);
        delete ee.inProgress, ee.toPosition = ee.toHandle ? ee.toHandle.position : null, _ == null || _(A, ee), b.setState({connectionClickStartHandle: null})
    };
    return c.jsx("div", {
        "data-handleid": x,
        "data-nodeid": y,
        "data-handlepos": t,
        "data-id": `${C}-${y}-${x}-${e}`,
        className: ze(["react-flow__handle", `react-flow__handle-${t}`, "nodrag", v, u, {
            source: !m,
            target: m,
            connectable: r,
            connectablestart: o,
            connectableend: s,
            clickconnecting: E,
            connectingfrom: S,
            connectingto: N,
            valid: L,
            connectionindicator: r && (!O || T) && (O || D ? s : o)
        }]),
        onMouseDown: B,
        onTouchStart: B,
        onClick: w ? k : void 0,
        ref: h, ...p,
        children: l
    })
}

const de = g.memo(mC(Q5));

function Z5({data: e, isConnectable: t, sourcePosition: n = q.Bottom}) {
    return c.jsxs(c.Fragment, {
        children: [e == null ? void 0 : e.label, c.jsx(de, {
            type: "source",
            position: n,
            isConnectable: t
        })]
    })
}

function J5({data: e, isConnectable: t, targetPosition: n = q.Top, sourcePosition: r = q.Bottom}) {
    return c.jsxs(c.Fragment, {
        children: [c.jsx(de, {
            type: "target",
            position: n,
            isConnectable: t
        }), e == null ? void 0 : e.label, c.jsx(de, {type: "source", position: r, isConnectable: t})]
    })
}

function eO() {
    return null
}

function tO({data: e, isConnectable: t, targetPosition: n = q.Top}) {
    return c.jsxs(c.Fragment, {
        children: [c.jsx(de, {
            type: "target",
            position: n,
            isConnectable: t
        }), e == null ? void 0 : e.label]
    })
}

const Uc = {ArrowUp: {x: 0, y: -1}, ArrowDown: {x: 0, y: 1}, ArrowLeft: {x: -1, y: 0}, ArrowRight: {x: 1, y: 0}},
    E0 = {input: Z5, default: J5, output: tO, group: eO};

function nO(e) {
    var t, n, r, o;
    return e.internals.handleBounds === void 0 ? {
        width: e.width ?? e.initialWidth ?? ((t = e.style) == null ? void 0 : t.width),
        height: e.height ?? e.initialHeight ?? ((n = e.style) == null ? void 0 : n.height)
    } : {
        width: e.width ?? ((r = e.style) == null ? void 0 : r.width),
        height: e.height ?? ((o = e.style) == null ? void 0 : o.height)
    }
}

const rO = e => {
    const {width: t, height: n, x: r, y: o} = Oa(e.nodeLookup, {filter: s => !!s.selected});
    return {
        width: an(t) ? t : null,
        height: an(n) ? n : null,
        userSelectionActive: e.userSelectionActive,
        transformString: `translate(${e.transform[0]}px,${e.transform[1]}px) scale(${e.transform[2]}) translate(${r}px,${o}px)`
    }
};

function oO({onSelectionContextMenu: e, noPanClassName: t, disableKeyboardA11y: n}) {
    const r = Te(), {width: o, height: s, transformString: i, userSelectionActive: a} = me(rO, $e), l = yC(),
        u = g.useRef(null);
    if (g.useEffect(() => {
        var p;
        n || (p = u.current) == null || p.focus({preventScroll: !0})
    }, [n]), vC({nodeRef: u}), a || !o || !s) return null;
    const d = e ? p => {
        const h = r.getState().nodes.filter(x => x.selected);
        e(p, h)
    } : void 0, f = p => {
        Object.prototype.hasOwnProperty.call(Uc, p.key) && (p.preventDefault(), l({
            direction: Uc[p.key],
            factor: p.shiftKey ? 4 : 1
        }))
    };
    return c.jsx("div", {
        className: ze(["react-flow__nodesselection", "react-flow__container", t]),
        style: {transform: i},
        children: c.jsx("div", {
            ref: u,
            className: "react-flow__nodesselection-rect",
            onContextMenu: d,
            tabIndex: n ? void 0 : -1,
            onKeyDown: n ? void 0 : f,
            style: {width: o, height: s}
        })
    })
}

const k0 = typeof window < "u" ? window : void 0,
    sO = e => ({nodesSelectionActive: e.nodesSelectionActive, userSelectionActive: e.userSelectionActive});

function wC({
                children: e,
                onPaneClick: t,
                onPaneMouseEnter: n,
                onPaneMouseMove: r,
                onPaneMouseLeave: o,
                onPaneContextMenu: s,
                onPaneScroll: i,
                paneClickDistance: a,
                deleteKeyCode: l,
                selectionKeyCode: u,
                selectionOnDrag: d,
                selectionMode: f,
                onSelectionStart: p,
                onSelectionEnd: h,
                multiSelectionKeyCode: x,
                panActivationKeyCode: m,
                zoomActivationKeyCode: b,
                elementsSelectable: y,
                zoomOnScroll: w,
                zoomOnPinch: v,
                panOnScroll: C,
                panOnScrollSpeed: S,
                panOnScrollMode: N,
                zoomOnDoubleClick: E,
                panOnDrag: T,
                defaultViewport: O,
                translateExtent: D,
                minZoom: L,
                maxZoom: $,
                preventScrolling: B,
                onSelectionContextMenu: k,
                noWheelClassName: M,
                noPanClassName: j,
                disableKeyboardA11y: A,
                onViewportChange: P,
                isControlledViewport: _
            }) {
    const {nodesSelectionActive: R, userSelectionActive: z} = me(sO), I = va(u, {target: k0}), V = va(m, {target: k0}),
        H = V || T, U = V || C, K = d && H !== !0, F = I || z || K;
    return F5({deleteKeyCode: l, multiSelectionKeyCode: x}), c.jsx(V5, {
        onPaneContextMenu: s,
        elementsSelectable: y,
        zoomOnScroll: w,
        zoomOnPinch: v,
        panOnScroll: U,
        panOnScrollSpeed: S,
        panOnScrollMode: N,
        zoomOnDoubleClick: E,
        panOnDrag: !I && H,
        defaultViewport: O,
        translateExtent: D,
        minZoom: L,
        maxZoom: $,
        zoomActivationKeyCode: b,
        preventScrolling: B,
        noWheelClassName: M,
        noPanClassName: j,
        onViewportChange: P,
        isControlledViewport: _,
        paneClickDistance: a,
        children: c.jsxs(K5, {
            onSelectionStart: p,
            onSelectionEnd: h,
            onPaneClick: t,
            onPaneMouseEnter: n,
            onPaneMouseMove: r,
            onPaneMouseLeave: o,
            onPaneContextMenu: s,
            onPaneScroll: i,
            panOnDrag: H,
            isSelecting: !!F,
            selectionMode: f,
            selectionKeyPressed: I,
            selectionOnDrag: K,
            children: [e, R && c.jsx(oO, {onSelectionContextMenu: k, noPanClassName: j, disableKeyboardA11y: A})]
        })
    })
}

wC.displayName = "FlowRenderer";
const iO = g.memo(wC), aO = e => t => e ? Rb(t.nodeLookup, {
    x: 0,
    y: 0,
    width: t.width,
    height: t.height
}, t.transform, !0).map(n => n.id) : Array.from(t.nodeLookup.keys());

function lO(e) {
    return me(g.useCallback(aO(e), [e]), $e)
}

const cO = e => e.updateNodeInternals;

function uO() {
    const e = me(cO), [t] = g.useState(() => typeof ResizeObserver > "u" ? null : new ResizeObserver(n => {
        const r = new Map;
        n.forEach(o => {
            const s = o.target.getAttribute("data-id");
            r.set(s, {id: s, nodeElement: o.target, force: !0})
        }), e(r)
    }));
    return g.useEffect(() => () => {
        t == null || t.disconnect()
    }, [t]), t
}

function dO({node: e, nodeType: t, hasDimensions: n, resizeObserver: r}) {
    const o = Te(), s = g.useRef(null), i = g.useRef(null), a = g.useRef(e.sourcePosition),
        l = g.useRef(e.targetPosition), u = g.useRef(t), d = n && !!e.internals.handleBounds;
    return g.useEffect(() => {
        s.current && !e.hidden && (!d || i.current !== s.current) && (i.current && (r == null || r.unobserve(i.current)), r == null || r.observe(s.current), i.current = s.current)
    }, [d, e.hidden]), g.useEffect(() => () => {
        i.current && (r == null || r.unobserve(i.current), i.current = null)
    }, []), g.useEffect(() => {
        if (s.current) {
            const f = u.current !== t, p = a.current !== e.sourcePosition, h = l.current !== e.targetPosition;
            (f || p || h) && (u.current = t, a.current = e.sourcePosition, l.current = e.targetPosition, o.getState().updateNodeInternals(new Map([[e.id, {
                id: e.id,
                nodeElement: s.current,
                force: !0
            }]])))
        }
    }, [e.id, t, e.sourcePosition, e.targetPosition]), s
}

function fO({
                id: e,
                onClick: t,
                onMouseEnter: n,
                onMouseMove: r,
                onMouseLeave: o,
                onContextMenu: s,
                onDoubleClick: i,
                nodesDraggable: a,
                elementsSelectable: l,
                nodesConnectable: u,
                nodesFocusable: d,
                resizeObserver: f,
                noDragClassName: p,
                noPanClassName: h,
                disableKeyboardA11y: x,
                rfId: m,
                nodeTypes: b,
                nodeClickDistance: y,
                onError: w
            }) {
    const {node: v, internals: C, isParent: S} = me(F => {
        const Y = F.nodeLookup.get(e), X = F.parentLookup.has(e);
        return {node: Y, internals: Y.internals, isParent: X}
    }, $e);
    let N = v.type || "default", E = (b == null ? void 0 : b[N]) || E0[N];
    E === void 0 && (w == null || w("003", In.error003(N)), N = "default", E = E0.default);
    const T = !!(v.draggable || a && typeof v.draggable > "u"), O = !!(v.selectable || l && typeof v.selectable > "u"),
        D = !!(v.connectable || u && typeof v.connectable > "u"), L = !!(v.focusable || d && typeof v.focusable > "u"),
        $ = Te(), B = $b(v), k = dO({node: v, nodeType: N, hasDimensions: B, resizeObserver: f}), M = vC({
            nodeRef: k,
            disabled: v.hidden || !T,
            noDragClassName: p,
            handleSelector: v.dragHandle,
            nodeId: e,
            isSelectable: O,
            nodeClickDistance: y
        }), j = yC();
    if (v.hidden) return null;
    const A = sr(v), P = nO(v), _ = O || T || t || n || r || o, R = n ? F => n(F, {...C.userNode}) : void 0,
        z = r ? F => r(F, {...C.userNode}) : void 0, I = o ? F => o(F, {...C.userNode}) : void 0,
        V = s ? F => s(F, {...C.userNode}) : void 0, H = i ? F => i(F, {...C.userNode}) : void 0, U = F => {
            const {selectNodesOnDrag: Y, nodeDragThreshold: X} = $.getState();
            O && (!Y || !T || X > 0) && kp({id: e, store: $, nodeRef: k}), t && t(F, {...C.userNode})
        }, K = F => {
            if (!(zb(F.nativeEvent) || x)) if (jb.includes(F.key) && O) {
                const Y = F.key === "Escape";
                kp({id: e, store: $, unselect: Y, nodeRef: k})
            } else T && v.selected && Object.prototype.hasOwnProperty.call(Uc, F.key) && (F.preventDefault(), $.setState({ariaLiveMessage: `Moved selected node ${F.key.replace("Arrow", "").toLowerCase()}. New position, x: ${~~C.positionAbsolute.x}, y: ${~~C.positionAbsolute.y}`}), j({
                direction: Uc[F.key],
                factor: F.shiftKey ? 4 : 1
            }))
        };
    return c.jsx("div", {
        className: ze(["react-flow__node", `react-flow__node-${N}`, {[h]: T}, v.className, {
            selected: v.selected,
            selectable: O,
            parent: S,
            draggable: T,
            dragging: M
        }]),
        ref: k,
        style: {
            zIndex: C.z,
            transform: `translate(${C.positionAbsolute.x}px,${C.positionAbsolute.y}px)`,
            pointerEvents: _ ? "all" : "none",
            visibility: B ? "visible" : "hidden", ...v.style, ...P
        },
        "data-id": e,
        "data-testid": `rf__node-${e}`,
        onMouseEnter: R,
        onMouseMove: z,
        onMouseLeave: I,
        onContextMenu: V,
        onClick: U,
        onDoubleClick: H,
        onKeyDown: L ? K : void 0,
        tabIndex: L ? 0 : void 0,
        role: L ? "button" : void 0,
        "aria-describedby": x ? void 0 : `${cC}-${m}`,
        "aria-label": v.ariaLabel,
        children: c.jsx(G5, {
            value: e,
            children: c.jsx(E, {
                id: e,
                data: v.data,
                type: N,
                positionAbsoluteX: C.positionAbsolute.x,
                positionAbsoluteY: C.positionAbsolute.y,
                selected: v.selected ?? !1,
                selectable: O,
                draggable: T,
                deletable: v.deletable ?? !0,
                isConnectable: D,
                sourcePosition: v.sourcePosition,
                targetPosition: v.targetPosition,
                dragging: M,
                dragHandle: v.dragHandle,
                zIndex: C.z,
                parentId: v.parentId, ...A
            })
        })
    })
}

const pO = e => ({
    nodesDraggable: e.nodesDraggable,
    nodesConnectable: e.nodesConnectable,
    nodesFocusable: e.nodesFocusable,
    elementsSelectable: e.elementsSelectable,
    onError: e.onError
});

function bC(e) {
    const {nodesDraggable: t, nodesConnectable: n, nodesFocusable: r, elementsSelectable: o, onError: s} = me(pO, $e),
        i = lO(e.onlyRenderVisibleElements), a = uO();
    return c.jsx("div", {
        className: "react-flow__nodes",
        style: Lu,
        children: i.map(l => c.jsx(fO, {
            id: l,
            nodeTypes: e.nodeTypes,
            nodeExtent: e.nodeExtent,
            onClick: e.onNodeClick,
            onMouseEnter: e.onNodeMouseEnter,
            onMouseMove: e.onNodeMouseMove,
            onMouseLeave: e.onNodeMouseLeave,
            onContextMenu: e.onNodeContextMenu,
            onDoubleClick: e.onNodeDoubleClick,
            noDragClassName: e.noDragClassName,
            noPanClassName: e.noPanClassName,
            rfId: e.rfId,
            disableKeyboardA11y: e.disableKeyboardA11y,
            resizeObserver: a,
            nodesDraggable: t,
            nodesConnectable: n,
            nodesFocusable: r,
            elementsSelectable: o,
            nodeClickDistance: e.nodeClickDistance,
            onError: s
        }, l))
    })
}

bC.displayName = "NodeRenderer";
const hO = g.memo(bC);

function mO(e) {
    return me(g.useCallback(n => {
        if (!e) return n.edges.map(o => o.id);
        const r = [];
        if (n.width && n.height) for (const o of n.edges) {
            const s = n.nodeLookup.get(o.source), i = n.nodeLookup.get(o.target);
            s && i && n3({
                sourceNode: s,
                targetNode: i,
                width: n.width,
                height: n.height,
                transform: n.transform
            }) && r.push(o.id)
        }
        return r
    }, [e]), $e)
}

const gO = ({color: e = "none", strokeWidth: t = 1}) => c.jsx("polyline", {
    style: {stroke: e, strokeWidth: t},
    strokeLinecap: "round",
    strokeLinejoin: "round",
    fill: "none",
    points: "-5,-4 0,0 -5,4"
}), vO = ({color: e = "none", strokeWidth: t = 1}) => c.jsx("polyline", {
    style: {stroke: e, fill: e, strokeWidth: t},
    strokeLinecap: "round",
    strokeLinejoin: "round",
    points: "-5,-4 0,0 -5,4 -5,-4"
}), _0 = {[zc.Arrow]: gO, [zc.ArrowClosed]: vO};

function yO(e) {
    const t = Te();
    return g.useMemo(() => {
        var o, s;
        return Object.prototype.hasOwnProperty.call(_0, e) ? _0[e] : ((s = (o = t.getState()).onError) == null || s.call(o, "009", In.error009(e)), null)
    }, [e])
}

const xO = ({
                id: e,
                type: t,
                color: n,
                width: r = 12.5,
                height: o = 12.5,
                markerUnits: s = "strokeWidth",
                strokeWidth: i,
                orient: a = "auto-start-reverse"
            }) => {
    const l = yO(t);
    return l ? c.jsx("marker", {
        className: "react-flow__arrowhead",
        id: e,
        markerWidth: `${r}`,
        markerHeight: `${o}`,
        viewBox: "-10 -10 20 20",
        markerUnits: s,
        orient: a,
        refX: "0",
        refY: "0",
        children: c.jsx(l, {color: n, strokeWidth: i})
    }) : null
}, CC = ({defaultColor: e, rfId: t}) => {
    const n = me(s => s.edges), r = me(s => s.defaultEdgeOptions), o = g.useMemo(() => c3(n, {
        id: t,
        defaultColor: e,
        defaultMarkerStart: r == null ? void 0 : r.markerStart,
        defaultMarkerEnd: r == null ? void 0 : r.markerEnd
    }), [n, r, t, e]);
    return o.length ? c.jsx("svg", {
        className: "react-flow__marker",
        "aria-hidden": "true",
        children: c.jsx("defs", {
            children: o.map(s => c.jsx(xO, {
                id: s.id,
                type: s.type,
                color: s.color,
                width: s.width,
                height: s.height,
                markerUnits: s.markerUnits,
                strokeWidth: s.strokeWidth,
                orient: s.orient
            }, s.id))
        })
    }) : null
};
CC.displayName = "MarkerDefinitions";
var wO = g.memo(CC);

function SC({
                x: e,
                y: t,
                label: n,
                labelStyle: r,
                labelShowBg: o = !0,
                labelBgStyle: s,
                labelBgPadding: i = [2, 4],
                labelBgBorderRadius: a = 2,
                children: l,
                className: u,
                ...d
            }) {
    const [f, p] = g.useState({x: 1, y: 0, width: 0, height: 0}), h = ze(["react-flow__edge-textwrapper", u]),
        x = g.useRef(null);
    return g.useEffect(() => {
        if (x.current) {
            const m = x.current.getBBox();
            p({x: m.x, y: m.y, width: m.width, height: m.height})
        }
    }, [n]), n ? c.jsxs("g", {
        transform: `translate(${e - f.width / 2} ${t - f.height / 2})`,
        className: h,
        visibility: f.width ? "visible" : "hidden", ...d,
        children: [o && c.jsx("rect", {
            width: f.width + 2 * i[0],
            x: -i[0],
            y: -i[1],
            height: f.height + 2 * i[1],
            className: "react-flow__edge-textbg",
            style: s,
            rx: a,
            ry: a
        }), c.jsx("text", {
            className: "react-flow__edge-text",
            y: f.height / 2,
            dy: "0.3em",
            ref: x,
            style: r,
            children: n
        }), l]
    }) : null
}

SC.displayName = "EdgeText";
const bO = g.memo(SC);

function Fu({
                path: e,
                labelX: t,
                labelY: n,
                label: r,
                labelStyle: o,
                labelShowBg: s,
                labelBgStyle: i,
                labelBgPadding: a,
                labelBgBorderRadius: l,
                interactionWidth: u = 20,
                ...d
            }) {
    return c.jsxs(c.Fragment, {
        children: [c.jsx("path", {
            ...d,
            d: e,
            fill: "none",
            className: ze(["react-flow__edge-path", d.className])
        }), u && c.jsx("path", {
            d: e,
            fill: "none",
            strokeOpacity: 0,
            strokeWidth: u,
            className: "react-flow__edge-interaction"
        }), r && an(t) && an(n) ? c.jsx(bO, {
            x: t,
            y: n,
            label: r,
            labelStyle: o,
            labelShowBg: s,
            labelBgStyle: i,
            labelBgPadding: a,
            labelBgBorderRadius: l
        }) : null]
    })
}

function j0({pos: e, x1: t, y1: n, x2: r, y2: o}) {
    return e === q.Left || e === q.Right ? [.5 * (t + r), n] : [t, .5 * (n + o)]
}

function NC({sourceX: e, sourceY: t, sourcePosition: n = q.Bottom, targetX: r, targetY: o, targetPosition: s = q.Top}) {
    const [i, a] = j0({pos: n, x1: e, y1: t, x2: r, y2: o}), [l, u] = j0({
        pos: s,
        x1: r,
        y1: o,
        x2: e,
        y2: t
    }), [d, f, p, h] = Vb({
        sourceX: e,
        sourceY: t,
        targetX: r,
        targetY: o,
        sourceControlX: i,
        sourceControlY: a,
        targetControlX: l,
        targetControlY: u
    });
    return [`M${e},${t} C${i},${a} ${l},${u} ${r},${o}`, d, f, p, h]
}

function EC(e) {
    return g.memo(({
                       id: t,
                       sourceX: n,
                       sourceY: r,
                       targetX: o,
                       targetY: s,
                       sourcePosition: i,
                       targetPosition: a,
                       label: l,
                       labelStyle: u,
                       labelShowBg: d,
                       labelBgStyle: f,
                       labelBgPadding: p,
                       labelBgBorderRadius: h,
                       style: x,
                       markerEnd: m,
                       markerStart: b,
                       interactionWidth: y
                   }) => {
        const [w, v, C] = NC({sourceX: n, sourceY: r, sourcePosition: i, targetX: o, targetY: s, targetPosition: a}),
            S = e.isInternal ? void 0 : t;
        return c.jsx(Fu, {
            id: S,
            path: w,
            labelX: v,
            labelY: C,
            label: l,
            labelStyle: u,
            labelShowBg: d,
            labelBgStyle: f,
            labelBgPadding: p,
            labelBgBorderRadius: h,
            style: x,
            markerEnd: m,
            markerStart: b,
            interactionWidth: y
        })
    })
}

const CO = EC({isInternal: !1}), kC = EC({isInternal: !0});
CO.displayName = "SimpleBezierEdge";
kC.displayName = "SimpleBezierEdgeInternal";

function _C(e) {
    return g.memo(({
                       id: t,
                       sourceX: n,
                       sourceY: r,
                       targetX: o,
                       targetY: s,
                       label: i,
                       labelStyle: a,
                       labelShowBg: l,
                       labelBgStyle: u,
                       labelBgPadding: d,
                       labelBgBorderRadius: f,
                       style: p,
                       sourcePosition: h = q.Bottom,
                       targetPosition: x = q.Top,
                       markerEnd: m,
                       markerStart: b,
                       pathOptions: y,
                       interactionWidth: w
                   }) => {
        const [v, C, S] = Cp({
            sourceX: n,
            sourceY: r,
            sourcePosition: h,
            targetX: o,
            targetY: s,
            targetPosition: x,
            borderRadius: y == null ? void 0 : y.borderRadius,
            offset: y == null ? void 0 : y.offset
        }), N = e.isInternal ? void 0 : t;
        return c.jsx(Fu, {
            id: N,
            path: v,
            labelX: C,
            labelY: S,
            label: i,
            labelStyle: a,
            labelShowBg: l,
            labelBgStyle: u,
            labelBgPadding: d,
            labelBgBorderRadius: f,
            style: p,
            markerEnd: m,
            markerStart: b,
            interactionWidth: w
        })
    })
}

const jC = _C({isInternal: !1}), PC = _C({isInternal: !0});
jC.displayName = "SmoothStepEdge";
PC.displayName = "SmoothStepEdgeInternal";

function TC(e) {
    return g.memo(({id: t, ...n}) => {
        var o;
        const r = e.isInternal ? void 0 : t;
        return c.jsx(jC, {
            ...n, id: r, pathOptions: g.useMemo(() => {
                var s;
                return {borderRadius: 0, offset: (s = n.pathOptions) == null ? void 0 : s.offset}
            }, [(o = n.pathOptions) == null ? void 0 : o.offset])
        })
    })
}

const SO = TC({isInternal: !1}), MC = TC({isInternal: !0});
SO.displayName = "StepEdge";
MC.displayName = "StepEdgeInternal";

function RC(e) {
    return g.memo(({
                       id: t,
                       sourceX: n,
                       sourceY: r,
                       targetX: o,
                       targetY: s,
                       label: i,
                       labelStyle: a,
                       labelShowBg: l,
                       labelBgStyle: u,
                       labelBgPadding: d,
                       labelBgBorderRadius: f,
                       style: p,
                       markerEnd: h,
                       markerStart: x,
                       interactionWidth: m
                   }) => {
        const [b, y, w] = Kb({sourceX: n, sourceY: r, targetX: o, targetY: s}), v = e.isInternal ? void 0 : t;
        return c.jsx(Fu, {
            id: v,
            path: b,
            labelX: y,
            labelY: w,
            label: i,
            labelStyle: a,
            labelShowBg: l,
            labelBgStyle: u,
            labelBgPadding: d,
            labelBgBorderRadius: f,
            style: p,
            markerEnd: h,
            markerStart: x,
            interactionWidth: m
        })
    })
}

const NO = RC({isInternal: !1}), AC = RC({isInternal: !0});
NO.displayName = "StraightEdge";
AC.displayName = "StraightEdgeInternal";

function IC(e) {
    return g.memo(({
                       id: t,
                       sourceX: n,
                       sourceY: r,
                       targetX: o,
                       targetY: s,
                       sourcePosition: i = q.Bottom,
                       targetPosition: a = q.Top,
                       label: l,
                       labelStyle: u,
                       labelShowBg: d,
                       labelBgStyle: f,
                       labelBgPadding: p,
                       labelBgBorderRadius: h,
                       style: x,
                       markerEnd: m,
                       markerStart: b,
                       pathOptions: y,
                       interactionWidth: w
                   }) => {
        const [v, C, S] = Hb({
            sourceX: n,
            sourceY: r,
            sourcePosition: i,
            targetX: o,
            targetY: s,
            targetPosition: a,
            curvature: y == null ? void 0 : y.curvature
        }), N = e.isInternal ? void 0 : t;
        return c.jsx(Fu, {
            id: N,
            path: v,
            labelX: C,
            labelY: S,
            label: l,
            labelStyle: u,
            labelShowBg: d,
            labelBgStyle: f,
            labelBgPadding: p,
            labelBgBorderRadius: h,
            style: x,
            markerEnd: m,
            markerStart: b,
            interactionWidth: w
        })
    })
}

const EO = IC({isInternal: !1}), OC = IC({isInternal: !0});
EO.displayName = "BezierEdge";
OC.displayName = "BezierEdgeInternal";
const P0 = {default: OC, straight: AC, step: MC, smoothstep: PC, simplebezier: kC},
    T0 = {sourceX: null, sourceY: null, targetX: null, targetY: null, sourcePosition: null, targetPosition: null},
    kO = (e, t, n) => n === q.Left ? e - t : n === q.Right ? e + t : e,
    _O = (e, t, n) => n === q.Top ? e - t : n === q.Bottom ? e + t : e, M0 = "react-flow__edgeupdater";

function R0({
                position: e,
                centerX: t,
                centerY: n,
                radius: r = 10,
                onMouseDown: o,
                onMouseEnter: s,
                onMouseOut: i,
                type: a
            }) {
    return c.jsx("circle", {
        onMouseDown: o,
        onMouseEnter: s,
        onMouseOut: i,
        className: ze([M0, `${M0}-${a}`]),
        cx: kO(t, r, e),
        cy: _O(n, r, e),
        r,
        stroke: "transparent",
        fill: "transparent"
    })
}

function jO({
                isReconnectable: e,
                reconnectRadius: t,
                edge: n,
                sourceX: r,
                sourceY: o,
                targetX: s,
                targetY: i,
                sourcePosition: a,
                targetPosition: l,
                onReconnect: u,
                onReconnectStart: d,
                onReconnectEnd: f,
                setReconnecting: p,
                setUpdateHover: h
            }) {
    const x = Te(), m = (C, S) => {
            if (C.button !== 0) return;
            const {
                autoPanOnConnect: N,
                domNode: E,
                isValidConnection: T,
                connectionMode: O,
                connectionRadius: D,
                lib: L,
                onConnectStart: $,
                onConnectEnd: B,
                cancelConnection: k,
                nodeLookup: M,
                rfId: j,
                panBy: A,
                updateConnection: P
            } = x.getState(), _ = S.type === "target";
            p(!0), d == null || d(C, n, S.type);
            const R = (I, V) => {
                p(!1), f == null || f(I, n, S.type, V)
            }, z = I => u == null ? void 0 : u(n, I);
            Ep.onPointerDown(C.nativeEvent, {
                autoPanOnConnect: N,
                connectionMode: O,
                connectionRadius: D,
                domNode: E,
                handleId: S.id,
                nodeId: S.nodeId,
                nodeLookup: M,
                isTarget: _,
                edgeUpdaterType: S.type,
                lib: L,
                flowId: j,
                cancelConnection: k,
                panBy: A,
                isValidConnection: T,
                onConnect: z,
                onConnectStart: $,
                onConnectEnd: B,
                onReconnectEnd: R,
                updateConnection: P,
                getTransform: () => x.getState().transform,
                getFromHandle: () => x.getState().connection.fromHandle
            })
        }, b = C => m(C, {nodeId: n.target, id: n.targetHandle ?? null, type: "target"}),
        y = C => m(C, {nodeId: n.source, id: n.sourceHandle ?? null, type: "source"}), w = () => h(!0), v = () => h(!1);
    return c.jsxs(c.Fragment, {
        children: [(e === !0 || e === "source") && c.jsx(R0, {
            position: a,
            centerX: r,
            centerY: o,
            radius: t,
            onMouseDown: b,
            onMouseEnter: w,
            onMouseOut: v,
            type: "source"
        }), (e === !0 || e === "target") && c.jsx(R0, {
            position: l,
            centerX: s,
            centerY: i,
            radius: t,
            onMouseDown: y,
            onMouseEnter: w,
            onMouseOut: v,
            type: "target"
        })]
    })
}

function PO({
                id: e,
                edgesFocusable: t,
                edgesReconnectable: n,
                elementsSelectable: r,
                onClick: o,
                onDoubleClick: s,
                onContextMenu: i,
                onMouseEnter: a,
                onMouseMove: l,
                onMouseLeave: u,
                reconnectRadius: d,
                onReconnect: f,
                onReconnectStart: p,
                onReconnectEnd: h,
                rfId: x,
                edgeTypes: m,
                noPanClassName: b,
                onError: y,
                disableKeyboardA11y: w
            }) {
    let v = me(Q => Q.edgeLookup.get(e));
    const C = me(Q => Q.defaultEdgeOptions);
    v = C ? {...C, ...v} : v;
    let S = v.type || "default", N = (m == null ? void 0 : m[S]) || P0[S];
    N === void 0 && (y == null || y("011", In.error011(S)), S = "default", N = P0.default);
    const E = !!(v.focusable || t && typeof v.focusable > "u"),
        T = typeof f < "u" && (v.reconnectable || n && typeof v.reconnectable > "u"),
        O = !!(v.selectable || r && typeof v.selectable > "u"),
        D = g.useRef(null), [L, $] = g.useState(!1), [B, k] = g.useState(!1), M = Te(), {
            zIndex: j,
            sourceX: A,
            sourceY: P,
            targetX: _,
            targetY: R,
            sourcePosition: z,
            targetPosition: I
        } = me(g.useCallback(Q => {
            const ue = Q.nodeLookup.get(v.source), ie = Q.nodeLookup.get(v.target);
            if (!ue || !ie) return {zIndex: v.zIndex, ...T0};
            const we = l3({
                id: e,
                sourceNode: ue,
                targetNode: ie,
                sourceHandle: v.sourceHandle || null,
                targetHandle: v.targetHandle || null,
                connectionMode: Q.connectionMode,
                onError: y
            });
            return {
                zIndex: t3({
                    selected: v.selected,
                    zIndex: v.zIndex,
                    sourceNode: ue,
                    targetNode: ie,
                    elevateOnSelect: Q.elevateEdgesOnSelect
                }), ...we || T0
            }
        }, [v.source, v.target, v.sourceHandle, v.targetHandle, v.selected, v.zIndex]), $e),
        V = g.useMemo(() => v.markerStart ? `url('#${Sp(v.markerStart, x)}')` : void 0, [v.markerStart, x]),
        H = g.useMemo(() => v.markerEnd ? `url('#${Sp(v.markerEnd, x)}')` : void 0, [v.markerEnd, x]);
    if (v.hidden || A === null || P === null || _ === null || R === null) return null;
    const U = Q => {
        var Ne;
        const {addSelectedEdges: ue, unselectNodesAndEdges: ie, multiSelectionActive: we} = M.getState();
        O && (M.setState({nodesSelectionActive: !1}), v.selected && we ? (ie({
            nodes: [],
            edges: [v]
        }), (Ne = D.current) == null || Ne.blur()) : ue([e])), o && o(Q, v)
    }, K = s ? Q => {
        s(Q, {...v})
    } : void 0, F = i ? Q => {
        i(Q, {...v})
    } : void 0, Y = a ? Q => {
        a(Q, {...v})
    } : void 0, X = l ? Q => {
        l(Q, {...v})
    } : void 0, te = u ? Q => {
        u(Q, {...v})
    } : void 0, ee = Q => {
        var ue;
        if (!w && jb.includes(Q.key) && O) {
            const {unselectNodesAndEdges: ie, addSelectedEdges: we} = M.getState();
            Q.key === "Escape" ? ((ue = D.current) == null || ue.blur(), ie({edges: [v]})) : we([e])
        }
    };
    return c.jsx("svg", {
        style: {zIndex: j}, children: c.jsxs("g", {
            className: ze(["react-flow__edge", `react-flow__edge-${S}`, v.className, b, {
                selected: v.selected,
                animated: v.animated,
                inactive: !O && !o,
                updating: L,
                selectable: O
            }]),
            onClick: U,
            onDoubleClick: K,
            onContextMenu: F,
            onMouseEnter: Y,
            onMouseMove: X,
            onMouseLeave: te,
            onKeyDown: E ? ee : void 0,
            tabIndex: E ? 0 : void 0,
            role: E ? "button" : "img",
            "data-id": e,
            "data-testid": `rf__edge-${e}`,
            "aria-label": v.ariaLabel === null ? void 0 : v.ariaLabel || `Edge from ${v.source} to ${v.target}`,
            "aria-describedby": E ? `${uC}-${x}` : void 0,
            ref: D,
            children: [!B && c.jsx(N, {
                id: e,
                source: v.source,
                target: v.target,
                type: v.type,
                selected: v.selected,
                animated: v.animated,
                selectable: O,
                deletable: v.deletable ?? !0,
                label: v.label,
                labelStyle: v.labelStyle,
                labelShowBg: v.labelShowBg,
                labelBgStyle: v.labelBgStyle,
                labelBgPadding: v.labelBgPadding,
                labelBgBorderRadius: v.labelBgBorderRadius,
                sourceX: A,
                sourceY: P,
                targetX: _,
                targetY: R,
                sourcePosition: z,
                targetPosition: I,
                data: v.data,
                style: v.style,
                sourceHandleId: v.sourceHandle,
                targetHandleId: v.targetHandle,
                markerStart: V,
                markerEnd: H,
                pathOptions: "pathOptions" in v ? v.pathOptions : void 0,
                interactionWidth: v.interactionWidth
            }), T && c.jsx(jO, {
                edge: v,
                isReconnectable: T,
                reconnectRadius: d,
                onReconnect: f,
                onReconnectStart: p,
                onReconnectEnd: h,
                sourceX: A,
                sourceY: P,
                targetX: _,
                targetY: R,
                sourcePosition: z,
                targetPosition: I,
                setUpdateHover: $,
                setReconnecting: k
            })]
        })
    })
}

const TO = e => ({
    edgesFocusable: e.edgesFocusable,
    edgesReconnectable: e.edgesReconnectable,
    elementsSelectable: e.elementsSelectable,
    connectionMode: e.connectionMode,
    onError: e.onError
});

function DC({
                defaultMarkerColor: e,
                onlyRenderVisibleElements: t,
                rfId: n,
                edgeTypes: r,
                noPanClassName: o,
                onReconnect: s,
                onEdgeContextMenu: i,
                onEdgeMouseEnter: a,
                onEdgeMouseMove: l,
                onEdgeMouseLeave: u,
                onEdgeClick: d,
                reconnectRadius: f,
                onEdgeDoubleClick: p,
                onReconnectStart: h,
                onReconnectEnd: x,
                disableKeyboardA11y: m
            }) {
    const {edgesFocusable: b, edgesReconnectable: y, elementsSelectable: w, onError: v} = me(TO, $e), C = mO(t);
    return c.jsxs("div", {
        className: "react-flow__edges",
        children: [c.jsx(wO, {defaultColor: e, rfId: n}), C.map(S => c.jsx(PO, {
            id: S,
            edgesFocusable: b,
            edgesReconnectable: y,
            elementsSelectable: w,
            noPanClassName: o,
            onReconnect: s,
            onContextMenu: i,
            onMouseEnter: a,
            onMouseMove: l,
            onMouseLeave: u,
            onClick: d,
            reconnectRadius: f,
            onDoubleClick: p,
            onReconnectStart: h,
            onReconnectEnd: x,
            rfId: n,
            onError: v,
            edgeTypes: r,
            disableKeyboardA11y: m
        }, S))]
    })
}

DC.displayName = "EdgeRenderer";
const MO = g.memo(DC), RO = e => `translate(${e.transform[0]}px,${e.transform[1]}px) scale(${e.transform[2]})`;

function AO({children: e}) {
    const t = me(RO);
    return c.jsx("div", {
        className: "react-flow__viewport xyflow__viewport react-flow__container",
        style: {transform: t},
        children: e
    })
}

function IO(e) {
    const t = $t(), n = g.useRef(!1);
    g.useEffect(() => {
        !n.current && t.viewportInitialized && e && (setTimeout(() => e(t), 1), n.current = !0)
    }, [e, t.viewportInitialized])
}

const OO = e => {
    var t;
    return (t = e.panZoom) == null ? void 0 : t.syncViewport
};

function DO(e) {
    const t = me(OO), n = Te();
    return g.useEffect(() => {
        e && (t == null || t(e), n.setState({transform: [e.x, e.y, e.zoom]}))
    }, [e, t]), null
}

function $O(e) {
    return e.connection.inProgress ? {...e.connection, to: Da(e.connection.to, e.transform)} : {...e.connection}
}

function LO(e) {
    return $O
}

function FO(e) {
    const t = LO();
    return me(t, $e)
}

const zO = e => ({
    nodesConnectable: e.nodesConnectable,
    isValid: e.connection.isValid,
    inProgress: e.connection.inProgress,
    width: e.width,
    height: e.height
});

function BO({containerStyle: e, style: t, type: n, component: r}) {
    const {nodesConnectable: o, width: s, height: i, isValid: a, inProgress: l} = me(zO, $e);
    return !(s && o && l) ? null : c.jsx("svg", {
        style: e,
        width: s,
        height: i,
        className: "react-flow__connectionline react-flow__container",
        children: c.jsx("g", {
            className: ze(["react-flow__connection", Tb(a)]),
            children: c.jsx($C, {style: t, type: n, CustomComponent: r, isValid: a})
        })
    })
}

const $C = ({style: e, type: t = kr.Bezier, CustomComponent: n, isValid: r}) => {
    const {
        inProgress: o,
        from: s,
        fromNode: i,
        fromHandle: a,
        fromPosition: l,
        to: u,
        toNode: d,
        toHandle: f,
        toPosition: p
    } = FO();
    if (!o) return;
    if (n) return c.jsx(n, {
        connectionLineType: t,
        connectionLineStyle: e,
        fromNode: i,
        fromHandle: a,
        fromX: s.x,
        fromY: s.y,
        toX: u.x,
        toY: u.y,
        fromPosition: l,
        toPosition: p,
        connectionStatus: Tb(r),
        toNode: d,
        toHandle: f
    });
    let h = "";
    const x = {sourceX: s.x, sourceY: s.y, sourcePosition: l, targetX: u.x, targetY: u.y, targetPosition: p};
    switch (t) {
        case kr.Bezier:
            [h] = Hb(x);
            break;
        case kr.SimpleBezier:
            [h] = NC(x);
            break;
        case kr.Step:
            [h] = Cp({...x, borderRadius: 0});
            break;
        case kr.SmoothStep:
            [h] = Cp(x);
            break;
        default:
            [h] = Kb(x)
    }
    return c.jsx("path", {d: h, fill: "none", className: "react-flow__connection-path", style: e})
};
$C.displayName = "ConnectionLine";
const VO = {};

function A0(e = VO) {
    g.useRef(e), Te(), g.useEffect(() => {
    }, [e])
}

function HO() {
    Te(), g.useRef(!1), g.useEffect(() => {
    }, [])
}

function LC({
                nodeTypes: e,
                edgeTypes: t,
                onInit: n,
                onNodeClick: r,
                onEdgeClick: o,
                onNodeDoubleClick: s,
                onEdgeDoubleClick: i,
                onNodeMouseEnter: a,
                onNodeMouseMove: l,
                onNodeMouseLeave: u,
                onNodeContextMenu: d,
                onSelectionContextMenu: f,
                onSelectionStart: p,
                onSelectionEnd: h,
                connectionLineType: x,
                connectionLineStyle: m,
                connectionLineComponent: b,
                connectionLineContainerStyle: y,
                selectionKeyCode: w,
                selectionOnDrag: v,
                selectionMode: C,
                multiSelectionKeyCode: S,
                panActivationKeyCode: N,
                zoomActivationKeyCode: E,
                deleteKeyCode: T,
                onlyRenderVisibleElements: O,
                elementsSelectable: D,
                defaultViewport: L,
                translateExtent: $,
                minZoom: B,
                maxZoom: k,
                preventScrolling: M,
                defaultMarkerColor: j,
                zoomOnScroll: A,
                zoomOnPinch: P,
                panOnScroll: _,
                panOnScrollSpeed: R,
                panOnScrollMode: z,
                zoomOnDoubleClick: I,
                panOnDrag: V,
                onPaneClick: H,
                onPaneMouseEnter: U,
                onPaneMouseMove: K,
                onPaneMouseLeave: F,
                onPaneScroll: Y,
                onPaneContextMenu: X,
                paneClickDistance: te,
                nodeClickDistance: ee,
                onEdgeContextMenu: Q,
                onEdgeMouseEnter: ue,
                onEdgeMouseMove: ie,
                onEdgeMouseLeave: we,
                reconnectRadius: Ne,
                onReconnect: pt,
                onReconnectStart: $n,
                onReconnectEnd: yn,
                noDragClassName: cr,
                noWheelClassName: Ln,
                noPanClassName: ur,
                disableKeyboardA11y: Ct,
                nodeExtent: ri,
                rfId: dr,
                viewport: Be,
                onViewportChange: Gt
            }) {
    return A0(e), A0(t), HO(), IO(n), DO(Be), c.jsx(iO, {
        onPaneClick: H,
        onPaneMouseEnter: U,
        onPaneMouseMove: K,
        onPaneMouseLeave: F,
        onPaneContextMenu: X,
        onPaneScroll: Y,
        paneClickDistance: te,
        deleteKeyCode: T,
        selectionKeyCode: w,
        selectionOnDrag: v,
        selectionMode: C,
        onSelectionStart: p,
        onSelectionEnd: h,
        multiSelectionKeyCode: S,
        panActivationKeyCode: N,
        zoomActivationKeyCode: E,
        elementsSelectable: D,
        zoomOnScroll: A,
        zoomOnPinch: P,
        zoomOnDoubleClick: I,
        panOnScroll: _,
        panOnScrollSpeed: R,
        panOnScrollMode: z,
        panOnDrag: V,
        defaultViewport: L,
        translateExtent: $,
        minZoom: B,
        maxZoom: k,
        onSelectionContextMenu: f,
        preventScrolling: M,
        noDragClassName: cr,
        noWheelClassName: Ln,
        noPanClassName: ur,
        disableKeyboardA11y: Ct,
        onViewportChange: Gt,
        isControlledViewport: !!Be,
        children: c.jsxs(AO, {
            children: [c.jsx(MO, {
                edgeTypes: t,
                onEdgeClick: o,
                onEdgeDoubleClick: i,
                onReconnect: pt,
                onReconnectStart: $n,
                onReconnectEnd: yn,
                onlyRenderVisibleElements: O,
                onEdgeContextMenu: Q,
                onEdgeMouseEnter: ue,
                onEdgeMouseMove: ie,
                onEdgeMouseLeave: we,
                reconnectRadius: Ne,
                defaultMarkerColor: j,
                noPanClassName: ur,
                disableKeyboardA11y: Ct,
                rfId: dr
            }), c.jsx(BO, {
                style: m,
                type: x,
                component: b,
                containerStyle: y
            }), c.jsx("div", {className: "react-flow__edgelabel-renderer"}), c.jsx(hO, {
                nodeTypes: e,
                onNodeClick: r,
                onNodeDoubleClick: s,
                onNodeMouseEnter: a,
                onNodeMouseMove: l,
                onNodeMouseLeave: u,
                onNodeContextMenu: d,
                nodeClickDistance: ee,
                onlyRenderVisibleElements: O,
                noPanClassName: ur,
                noDragClassName: cr,
                disableKeyboardA11y: Ct,
                nodeExtent: ri,
                rfId: dr
            }), c.jsx("div", {className: "react-flow__viewport-portal"})]
        })
    })
}

LC.displayName = "GraphView";
const UO = g.memo(LC), I0 = ({
                                 nodes: e,
                                 edges: t,
                                 defaultNodes: n,
                                 defaultEdges: r,
                                 width: o,
                                 height: s,
                                 fitView: i,
                                 fitViewOptions: a,
                                 minZoom: l = .5,
                                 maxZoom: u = 2,
                                 nodeOrigin: d,
                                 nodeExtent: f
                             } = {}) => {
    const p = new Map, h = new Map, x = new Map, m = new Map, b = r ?? t ?? [], y = n ?? e ?? [], w = d ?? [0, 0],
        v = f ?? pa;
    Gb(x, m, b);
    const C = Np(y, p, h, {nodeOrigin: w, nodeExtent: v, elevateNodesOnSelect: !1});
    let S = [0, 0, 1];
    if (i && o && s) {
        const N = Oa(p, {filter: D => !!((D.width || D.initialWidth) && (D.height || D.initialHeight))}), {
            x: E,
            y: T,
            zoom: O
        } = dm(N, o, s, l, u, (a == null ? void 0 : a.padding) ?? .1);
        S = [E, T, O]
    }
    return {
        rfId: "1",
        width: 0,
        height: 0,
        transform: S,
        nodes: y,
        nodesInitialized: C,
        nodeLookup: p,
        parentLookup: h,
        edges: b,
        edgeLookup: m,
        connectionLookup: x,
        onNodesChange: null,
        onEdgesChange: null,
        hasDefaultNodes: n !== void 0,
        hasDefaultEdges: r !== void 0,
        panZoom: null,
        minZoom: l,
        maxZoom: u,
        translateExtent: pa,
        nodeExtent: v,
        nodesSelectionActive: !1,
        userSelectionActive: !1,
        userSelectionRect: null,
        connectionMode: Vs.Strict,
        domNode: null,
        paneDragging: !1,
        noPanClassName: "nopan",
        nodeOrigin: w,
        nodeDragThreshold: 1,
        snapGrid: [15, 15],
        snapToGrid: !1,
        nodesDraggable: !0,
        nodesConnectable: !0,
        nodesFocusable: !0,
        edgesFocusable: !0,
        edgesReconnectable: !0,
        elementsSelectable: !0,
        elevateNodesOnSelect: !0,
        elevateEdgesOnSelect: !1,
        selectNodesOnDrag: !0,
        multiSelectionActive: !1,
        fitViewQueued: i ?? !1,
        fitViewOptions: a,
        fitViewResolver: null,
        connection: {...Pb},
        connectionClickStartHandle: null,
        connectOnClick: !0,
        ariaLiveMessage: "",
        autoPanOnConnect: !0,
        autoPanOnNodeDrag: !0,
        autoPanSpeed: 15,
        connectionRadius: 20,
        onError: q4,
        isValidConnection: void 0,
        onSelectionChangeHandlers: [],
        lib: "react",
        debug: !1
    }
}, WO = ({
             nodes: e,
             edges: t,
             defaultNodes: n,
             defaultEdges: r,
             width: o,
             height: s,
             fitView: i,
             fitViewOptions: a,
             minZoom: l,
             maxZoom: u,
             nodeOrigin: d,
             nodeExtent: f
         }) => u5((p, h) => {
    async function x() {
        const {
            nodeLookup: m,
            panZoom: b,
            fitViewOptions: y,
            fitViewResolver: w,
            width: v,
            height: C,
            minZoom: S,
            maxZoom: N
        } = h();
        b && (await G4({
            nodes: m,
            width: v,
            height: C,
            panZoom: b,
            minZoom: S,
            maxZoom: N
        }, y), w == null || w.resolve(!0), p({fitViewResolver: null}))
    }

    return {
        ...I0({
            nodes: e,
            edges: t,
            width: o,
            height: s,
            fitView: i,
            fitViewOptions: a,
            minZoom: l,
            maxZoom: u,
            nodeOrigin: d,
            nodeExtent: f,
            defaultNodes: n,
            defaultEdges: r
        }), setNodes: m => {
            const {nodeLookup: b, parentLookup: y, nodeOrigin: w, elevateNodesOnSelect: v, fitViewQueued: C} = h(),
                S = Np(m, b, y, {nodeOrigin: w, nodeExtent: f, elevateNodesOnSelect: v, checkEquality: !0});
            C && S ? (x(), p({nodes: m, nodesInitialized: S, fitViewQueued: !1, fitViewOptions: void 0})) : p({
                nodes: m,
                nodesInitialized: S
            })
        }, setEdges: m => {
            const {connectionLookup: b, edgeLookup: y} = h();
            Gb(b, y, m), p({edges: m})
        }, setDefaultNodesAndEdges: (m, b) => {
            if (m) {
                const {setNodes: y} = h();
                y(m), p({hasDefaultNodes: !0})
            }
            if (b) {
                const {setEdges: y} = h();
                y(b), p({hasDefaultEdges: !0})
            }
        }, updateNodeInternals: m => {
            const {
                triggerNodeChanges: b,
                nodeLookup: y,
                parentLookup: w,
                domNode: v,
                nodeOrigin: C,
                nodeExtent: S,
                debug: N,
                fitViewQueued: E
            } = h(), {changes: T, updatedInternals: O} = h3(m, y, w, v, C, S);
            O && (d3(y, w, {nodeOrigin: C, nodeExtent: S}), E ? (x(), p({
                fitViewQueued: !1,
                fitViewOptions: void 0
            })) : p({}), (T == null ? void 0 : T.length) > 0 && (N && console.log("React Flow: trigger node changes", T), b == null || b(T)))
        }, updateNodePositions: (m, b = !1) => {
            const y = [], w = [], {nodeLookup: v, triggerNodeChanges: C} = h();
            for (const [S, N] of m) {
                const E = v.get(S),
                    T = !!(E != null && E.expandParent && (E != null && E.parentId) && (N != null && N.position)), O = {
                        id: S,
                        type: "position",
                        position: T ? {x: Math.max(0, N.position.x), y: Math.max(0, N.position.y)} : N.position,
                        dragging: b
                    };
                T && E.parentId && y.push({
                    id: S,
                    parentId: E.parentId,
                    rect: {
                        ...N.internals.positionAbsolute,
                        width: N.measured.width ?? 0,
                        height: N.measured.height ?? 0
                    }
                }), w.push(O)
            }
            if (y.length > 0) {
                const {parentLookup: S, nodeOrigin: N} = h(), E = gm(y, v, S, N);
                w.push(...E)
            }
            C(w)
        }, triggerNodeChanges: m => {
            const {onNodesChange: b, setNodes: y, nodes: w, hasDefaultNodes: v, debug: C} = h();
            if (m != null && m.length) {
                if (v) {
                    const S = pC(m, w);
                    y(S)
                }
                C && console.log("React Flow: trigger node changes", m), b == null || b(m)
            }
        }, triggerEdgeChanges: m => {
            const {onEdgesChange: b, setEdges: y, edges: w, hasDefaultEdges: v, debug: C} = h();
            if (m != null && m.length) {
                if (v) {
                    const S = hC(m, w);
                    y(S)
                }
                C && console.log("React Flow: trigger edge changes", m), b == null || b(m)
            }
        }, addSelectedNodes: m => {
            const {
                multiSelectionActive: b,
                edgeLookup: y,
                nodeLookup: w,
                triggerNodeChanges: v,
                triggerEdgeChanges: C
            } = h();
            if (b) {
                const S = m.map(N => lo(N, !0));
                v(S);
                return
            }
            v(us(w, new Set([...m]), !0)), C(us(y))
        }, addSelectedEdges: m => {
            const {
                multiSelectionActive: b,
                edgeLookup: y,
                nodeLookup: w,
                triggerNodeChanges: v,
                triggerEdgeChanges: C
            } = h();
            if (b) {
                const S = m.map(N => lo(N, !0));
                C(S);
                return
            }
            C(us(y, new Set([...m]))), v(us(w, new Set, !0))
        }, unselectNodesAndEdges: ({nodes: m, edges: b} = {}) => {
            const {edges: y, nodes: w, nodeLookup: v, triggerNodeChanges: C, triggerEdgeChanges: S} = h(), N = m || w,
                E = b || y, T = N.map(D => {
                    const L = v.get(D.id);
                    return L && (L.selected = !1), lo(D.id, !1)
                }), O = E.map(D => lo(D.id, !1));
            C(T), S(O)
        }, setMinZoom: m => {
            const {panZoom: b, maxZoom: y} = h();
            b == null || b.setScaleExtent([m, y]), p({minZoom: m})
        }, setMaxZoom: m => {
            const {panZoom: b, minZoom: y} = h();
            b == null || b.setScaleExtent([y, m]), p({maxZoom: m})
        }, setTranslateExtent: m => {
            var b;
            (b = h().panZoom) == null || b.setTranslateExtent(m), p({translateExtent: m})
        }, setPaneClickDistance: m => {
            var b;
            (b = h().panZoom) == null || b.setClickDistance(m)
        }, resetSelectedElements: () => {
            const {edges: m, nodes: b, triggerNodeChanges: y, triggerEdgeChanges: w, elementsSelectable: v} = h();
            if (!v) return;
            const C = b.reduce((N, E) => E.selected ? [...N, lo(E.id, !1)] : N, []),
                S = m.reduce((N, E) => E.selected ? [...N, lo(E.id, !1)] : N, []);
            y(C), w(S)
        }, setNodeExtent: m => {
            const {
                nodes: b,
                nodeLookup: y,
                parentLookup: w,
                nodeOrigin: v,
                elevateNodesOnSelect: C,
                nodeExtent: S
            } = h();
            m[0][0] === S[0][0] && m[0][1] === S[0][1] && m[1][0] === S[1][0] && m[1][1] === S[1][1] || (Np(b, y, w, {
                nodeOrigin: v,
                nodeExtent: m,
                elevateNodesOnSelect: C,
                checkEquality: !1
            }), p({nodeExtent: m}))
        }, panBy: m => {
            const {transform: b, width: y, height: w, panZoom: v, translateExtent: C} = h();
            return m3({delta: m, panZoom: v, transform: b, translateExtent: C, width: y, height: w})
        }, cancelConnection: () => {
            p({connection: {...Pb}})
        }, updateConnection: m => {
            p({connection: m})
        }, reset: () => p({...I0()})
    }
}, Object.is);

function FC({
                initialNodes: e,
                initialEdges: t,
                defaultNodes: n,
                defaultEdges: r,
                initialWidth: o,
                initialHeight: s,
                initialMinZoom: i,
                initialMaxZoom: a,
                initialFitViewOptions: l,
                fitView: u,
                nodeOrigin: d,
                nodeExtent: f,
                children: p
            }) {
    const [h] = g.useState(() => WO({
        nodes: e,
        edges: t,
        defaultNodes: n,
        defaultEdges: r,
        width: o,
        height: s,
        fitView: u,
        minZoom: i,
        maxZoom: a,
        fitViewOptions: l,
        nodeOrigin: d,
        nodeExtent: f
    }));
    return c.jsx(d5, {value: h, children: c.jsx(I5, {children: p})})
}

function KO({
                children: e,
                nodes: t,
                edges: n,
                defaultNodes: r,
                defaultEdges: o,
                width: s,
                height: i,
                fitView: a,
                fitViewOptions: l,
                minZoom: u,
                maxZoom: d,
                nodeOrigin: f,
                nodeExtent: p
            }) {
    return g.useContext(Du) ? c.jsx(c.Fragment, {children: e}) : c.jsx(FC, {
        initialNodes: t,
        initialEdges: n,
        defaultNodes: r,
        defaultEdges: o,
        initialWidth: s,
        initialHeight: i,
        fitView: a,
        initialFitViewOptions: l,
        initialMinZoom: u,
        initialMaxZoom: d,
        nodeOrigin: f,
        nodeExtent: p,
        children: e
    })
}

const YO = {width: "100%", height: "100%", overflow: "hidden", position: "relative", zIndex: 0};

function GO({
                nodes: e,
                edges: t,
                defaultNodes: n,
                defaultEdges: r,
                className: o,
                nodeTypes: s,
                edgeTypes: i,
                onNodeClick: a,
                onEdgeClick: l,
                onInit: u,
                onMove: d,
                onMoveStart: f,
                onMoveEnd: p,
                onConnect: h,
                onConnectStart: x,
                onConnectEnd: m,
                onClickConnectStart: b,
                onClickConnectEnd: y,
                onNodeMouseEnter: w,
                onNodeMouseMove: v,
                onNodeMouseLeave: C,
                onNodeContextMenu: S,
                onNodeDoubleClick: N,
                onNodeDragStart: E,
                onNodeDrag: T,
                onNodeDragStop: O,
                onNodesDelete: D,
                onEdgesDelete: L,
                onDelete: $,
                onSelectionChange: B,
                onSelectionDragStart: k,
                onSelectionDrag: M,
                onSelectionDragStop: j,
                onSelectionContextMenu: A,
                onSelectionStart: P,
                onSelectionEnd: _,
                onBeforeDelete: R,
                connectionMode: z,
                connectionLineType: I = kr.Bezier,
                connectionLineStyle: V,
                connectionLineComponent: H,
                connectionLineContainerStyle: U,
                deleteKeyCode: K = "Backspace",
                selectionKeyCode: F = "Shift",
                selectionOnDrag: Y = !1,
                selectionMode: X = ha.Full,
                panActivationKeyCode: te = "Space",
                multiSelectionKeyCode: ee = Hc() ? "Meta" : "Control",
                zoomActivationKeyCode: Q = Hc() ? "Meta" : "Control",
                snapToGrid: ue,
                snapGrid: ie,
                onlyRenderVisibleElements: we = !1,
                selectNodesOnDrag: Ne,
                nodesDraggable: pt,
                nodesConnectable: $n,
                nodesFocusable: yn,
                nodeOrigin: cr = dC,
                edgesFocusable: Ln,
                edgesReconnectable: ur,
                elementsSelectable: Ct = !0,
                defaultViewport: ri = N5,
                minZoom: dr = .5,
                maxZoom: Be = 2,
                translateExtent: Gt = pa,
                preventScrolling: td = !0,
                nodeExtent: oi,
                defaultMarkerColor: Ho = "#b1b1b7",
                zoomOnScroll: nd = !0,
                zoomOnPinch: Ga = !0,
                panOnScroll: si = !1,
                panOnScrollSpeed: fr = .5,
                panOnScrollMode: Xa = No.Free,
                zoomOnDoubleClick: pr = !0,
                panOnDrag: rd = !0,
                onPaneClick: od,
                onPaneMouseEnter: qa,
                onPaneMouseMove: Qa,
                onPaneMouseLeave: sd,
                onPaneScroll: ii,
                onPaneContextMenu: xn,
                paneClickDistance: Za = 0,
                nodeClickDistance: oe = 0,
                children: ye,
                onReconnect: be,
                onReconnectStart: St,
                onReconnectEnd: Nt,
                onEdgeContextMenu: wn,
                onEdgeDoubleClick: Fn,
                onEdgeMouseEnter: Et,
                onEdgeMouseMove: bn,
                onEdgeMouseLeave: BE,
                reconnectRadius: VE = 10,
                onNodesChange: HE,
                onEdgesChange: UE,
                noDragClassName: WE = "nodrag",
                noWheelClassName: KE = "nowheel",
                noPanClassName: Hm = "nopan",
                fitView: Um,
                fitViewOptions: Wm,
                connectOnClick: YE,
                attributionPosition: GE,
                proOptions: XE,
                defaultEdgeOptions: qE,
                elevateNodesOnSelect: QE,
                elevateEdgesOnSelect: ZE,
                disableKeyboardA11y: Km = !1,
                autoPanOnConnect: JE,
                autoPanOnNodeDrag: e2,
                autoPanSpeed: t2,
                connectionRadius: n2,
                isValidConnection: r2,
                onError: o2,
                style: s2,
                id: Ym,
                nodeDragThreshold: i2,
                viewport: a2,
                onViewportChange: l2,
                width: c2,
                height: u2,
                colorMode: d2 = "light",
                debug: f2,
                onScroll: Ja,
                ...p2
            }, h2) {
    const id = Ym || "1", m2 = j5(d2), g2 = g.useCallback(Gm => {
        Gm.currentTarget.scrollTo({top: 0, left: 0, behavior: "instant"}), Ja == null || Ja(Gm)
    }, [Ja]);
    return c.jsx("div", {
        "data-testid": "rf__wrapper", ...p2,
        onScroll: g2,
        style: {...s2, ...YO},
        ref: h2,
        className: ze(["react-flow", o, m2]),
        id: Ym,
        children: c.jsxs(KO, {
            nodes: e,
            edges: t,
            width: c2,
            height: u2,
            fitView: Um,
            fitViewOptions: Wm,
            minZoom: dr,
            maxZoom: Be,
            nodeOrigin: cr,
            nodeExtent: oi,
            children: [c.jsx(UO, {
                onInit: u,
                onNodeClick: a,
                onEdgeClick: l,
                onNodeMouseEnter: w,
                onNodeMouseMove: v,
                onNodeMouseLeave: C,
                onNodeContextMenu: S,
                onNodeDoubleClick: N,
                nodeTypes: s,
                edgeTypes: i,
                connectionLineType: I,
                connectionLineStyle: V,
                connectionLineComponent: H,
                connectionLineContainerStyle: U,
                selectionKeyCode: F,
                selectionOnDrag: Y,
                selectionMode: X,
                deleteKeyCode: K,
                multiSelectionKeyCode: ee,
                panActivationKeyCode: te,
                zoomActivationKeyCode: Q,
                onlyRenderVisibleElements: we,
                defaultViewport: ri,
                translateExtent: Gt,
                minZoom: dr,
                maxZoom: Be,
                preventScrolling: td,
                zoomOnScroll: nd,
                zoomOnPinch: Ga,
                zoomOnDoubleClick: pr,
                panOnScroll: si,
                panOnScrollSpeed: fr,
                panOnScrollMode: Xa,
                panOnDrag: rd,
                onPaneClick: od,
                onPaneMouseEnter: qa,
                onPaneMouseMove: Qa,
                onPaneMouseLeave: sd,
                onPaneScroll: ii,
                onPaneContextMenu: xn,
                paneClickDistance: Za,
                nodeClickDistance: oe,
                onSelectionContextMenu: A,
                onSelectionStart: P,
                onSelectionEnd: _,
                onReconnect: be,
                onReconnectStart: St,
                onReconnectEnd: Nt,
                onEdgeContextMenu: wn,
                onEdgeDoubleClick: Fn,
                onEdgeMouseEnter: Et,
                onEdgeMouseMove: bn,
                onEdgeMouseLeave: BE,
                reconnectRadius: VE,
                defaultMarkerColor: Ho,
                noDragClassName: WE,
                noWheelClassName: KE,
                noPanClassName: Hm,
                rfId: id,
                disableKeyboardA11y: Km,
                nodeExtent: oi,
                viewport: a2,
                onViewportChange: l2
            }), c.jsx(_5, {
                nodes: e,
                edges: t,
                defaultNodes: n,
                defaultEdges: r,
                onConnect: h,
                onConnectStart: x,
                onConnectEnd: m,
                onClickConnectStart: b,
                onClickConnectEnd: y,
                nodesDraggable: pt,
                nodesConnectable: $n,
                nodesFocusable: yn,
                edgesFocusable: Ln,
                edgesReconnectable: ur,
                elementsSelectable: Ct,
                elevateNodesOnSelect: QE,
                elevateEdgesOnSelect: ZE,
                minZoom: dr,
                maxZoom: Be,
                nodeExtent: oi,
                onNodesChange: HE,
                onEdgesChange: UE,
                snapToGrid: ue,
                snapGrid: ie,
                connectionMode: z,
                translateExtent: Gt,
                connectOnClick: YE,
                defaultEdgeOptions: qE,
                fitView: Um,
                fitViewOptions: Wm,
                onNodesDelete: D,
                onEdgesDelete: L,
                onDelete: $,
                onNodeDragStart: E,
                onNodeDrag: T,
                onNodeDragStop: O,
                onSelectionDrag: M,
                onSelectionDragStart: k,
                onSelectionDragStop: j,
                onMove: d,
                onMoveStart: f,
                onMoveEnd: p,
                noPanClassName: Hm,
                nodeOrigin: cr,
                rfId: id,
                autoPanOnConnect: JE,
                autoPanOnNodeDrag: e2,
                autoPanSpeed: t2,
                onError: o2,
                connectionRadius: n2,
                isValidConnection: r2,
                selectNodesOnDrag: Ne,
                nodeDragThreshold: i2,
                onBeforeDelete: R,
                paneClickDistance: Za,
                debug: f2
            }), c.jsx(S5, {onSelectionChange: B}), ye, c.jsx(y5, {proOptions: XE, position: GE}), c.jsx(g5, {
                rfId: id,
                disableKeyboardA11y: Km
            })]
        })
    })
}

var XO = mC(GO);

function qO(e) {
    const [t, n] = g.useState(e), r = g.useCallback(o => n(s => pC(o, s)), []);
    return [t, n, r]
}

function QO(e) {
    const [t, n] = g.useState(e), r = g.useCallback(o => n(s => hC(o, s)), []);
    return [t, n, r]
}

function ZO({dimensions: e, lineWidth: t, variant: n, className: r}) {
    return c.jsx("path", {
        strokeWidth: t,
        d: `M${e[0] / 2} 0 V${e[1]} M0 ${e[1] / 2} H${e[0]}`,
        className: ze(["react-flow__background-pattern", n, r])
    })
}

function JO({radius: e, className: t}) {
    return c.jsx("circle", {cx: e, cy: e, r: e, className: ze(["react-flow__background-pattern", "dots", t])})
}

var Vr;
(function (e) {
    e.Lines = "lines", e.Dots = "dots", e.Cross = "cross"
})(Vr || (Vr = {}));
const eD = {[Vr.Dots]: 1, [Vr.Lines]: 1, [Vr.Cross]: 6},
    tD = e => ({transform: e.transform, patternId: `pattern-${e.rfId}`});

function zC({
                id: e,
                variant: t = Vr.Dots,
                gap: n = 20,
                size: r,
                lineWidth: o = 1,
                offset: s = 0,
                color: i,
                bgColor: a,
                style: l,
                className: u,
                patternClassName: d
            }) {
    const f = g.useRef(null), {transform: p, patternId: h} = me(tD, $e), x = r || eD[t], m = t === Vr.Dots,
        b = t === Vr.Cross, y = Array.isArray(n) ? n : [n, n], w = [y[0] * p[2] || 1, y[1] * p[2] || 1], v = x * p[2],
        C = Array.isArray(s) ? s : [s, s], S = b ? [v, v] : w,
        N = [C[0] * p[2] || 1 + S[0] / 2, C[1] * p[2] || 1 + S[1] / 2], E = `${h}${e || ""}`;
    return c.jsxs("svg", {
        className: ze(["react-flow__background", u]),
        style: {...l, ...Lu, "--xy-background-color-props": a, "--xy-background-pattern-color-props": i},
        ref: f,
        "data-testid": "rf__background",
        children: [c.jsx("pattern", {
            id: E,
            x: p[0] % w[0],
            y: p[1] % w[1],
            width: w[0],
            height: w[1],
            patternUnits: "userSpaceOnUse",
            patternTransform: `translate(-${N[0]},-${N[1]})`,
            children: m ? c.jsx(JO, {radius: v / 2, className: d}) : c.jsx(ZO, {
                dimensions: S,
                lineWidth: o,
                variant: t,
                className: d
            })
        }), c.jsx("rect", {x: "0", y: "0", width: "100%", height: "100%", fill: `url(#${E})`})]
    })
}

zC.displayName = "Background";
const nD = g.memo(zC);

function rD() {
    return c.jsx("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 32 32",
        children: c.jsx("path", {d: "M32 18.133H18.133V32h-4.266V18.133H0v-4.266h13.867V0h4.266v13.867H32z"})
    })
}

function oD() {
    return c.jsx("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 32 5",
        children: c.jsx("path", {d: "M0 0h32v4.2H0z"})
    })
}

function sD() {
    return c.jsx("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 32 30",
        children: c.jsx("path", {d: "M3.692 4.63c0-.53.4-.938.939-.938h5.215V0H4.708C2.13 0 0 2.054 0 4.63v5.216h3.692V4.631zM27.354 0h-5.2v3.692h5.17c.53 0 .984.4.984.939v5.215H32V4.631A4.624 4.624 0 0027.354 0zm.954 24.83c0 .532-.4.94-.939.94h-5.215v3.768h5.215c2.577 0 4.631-2.13 4.631-4.707v-5.139h-3.692v5.139zm-23.677.94c-.531 0-.939-.4-.939-.94v-5.138H0v5.139c0 2.577 2.13 4.707 4.708 4.707h5.138V25.77H4.631z"})
    })
}

function iD() {
    return c.jsx("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 25 32",
        children: c.jsx("path", {d: "M21.333 10.667H19.81V7.619C19.81 3.429 16.38 0 12.19 0 8 0 4.571 3.429 4.571 7.619v3.048H3.048A3.056 3.056 0 000 13.714v15.238A3.056 3.056 0 003.048 32h18.285a3.056 3.056 0 003.048-3.048V13.714a3.056 3.056 0 00-3.048-3.047zM12.19 24.533a3.056 3.056 0 01-3.047-3.047 3.056 3.056 0 013.047-3.048 3.056 3.056 0 013.048 3.048 3.056 3.056 0 01-3.048 3.047zm4.724-13.866H7.467V7.619c0-2.59 2.133-4.724 4.723-4.724 2.591 0 4.724 2.133 4.724 4.724v3.048z"})
    })
}

function aD() {
    return c.jsx("svg", {
        xmlns: "http://www.w3.org/2000/svg",
        viewBox: "0 0 25 32",
        children: c.jsx("path", {d: "M21.333 10.667H19.81V7.619C19.81 3.429 16.38 0 12.19 0c-4.114 1.828-1.37 2.133.305 2.438 1.676.305 4.42 2.59 4.42 5.181v3.048H3.047A3.056 3.056 0 000 13.714v15.238A3.056 3.056 0 003.048 32h18.285a3.056 3.056 0 003.048-3.048V13.714a3.056 3.056 0 00-3.048-3.047zM12.19 24.533a3.056 3.056 0 01-3.047-3.047 3.056 3.056 0 013.047-3.048 3.056 3.056 0 013.048 3.048 3.056 3.056 0 01-3.048 3.047z"})
    })
}

function Ml({children: e, className: t, ...n}) {
    return c.jsx("button", {type: "button", className: ze(["react-flow__controls-button", t]), ...n, children: e})
}

const lD = e => ({
    isInteractive: e.nodesDraggable || e.nodesConnectable || e.elementsSelectable,
    minZoomReached: e.transform[2] <= e.minZoom,
    maxZoomReached: e.transform[2] >= e.maxZoom
});

function BC({
                style: e,
                showZoom: t = !0,
                showFitView: n = !0,
                showInteractive: r = !0,
                fitViewOptions: o,
                onZoomIn: s,
                onZoomOut: i,
                onFitView: a,
                onInteractiveChange: l,
                className: u,
                children: d,
                position: f = "bottom-left",
                orientation: p = "vertical",
                "aria-label": h = "React Flow controls"
            }) {
    const x = Te(), {isInteractive: m, minZoomReached: b, maxZoomReached: y} = me(lD, $e), {
        zoomIn: w,
        zoomOut: v,
        fitView: C
    } = $t(), S = () => {
        w(), s == null || s()
    }, N = () => {
        v(), i == null || i()
    }, E = () => {
        C(o), a == null || a()
    }, T = () => {
        x.setState({nodesDraggable: !m, nodesConnectable: !m, elementsSelectable: !m}), l == null || l(!m)
    }, O = p === "horizontal" ? "horizontal" : "vertical";
    return c.jsxs($u, {
        className: ze(["react-flow__controls", O, u]),
        position: f,
        style: e,
        "data-testid": "rf__controls",
        "aria-label": h,
        children: [t && c.jsxs(c.Fragment, {
            children: [c.jsx(Ml, {
                onClick: S,
                className: "react-flow__controls-zoomin",
                title: "zoom in",
                "aria-label": "zoom in",
                disabled: y,
                children: c.jsx(rD, {})
            }), c.jsx(Ml, {
                onClick: N,
                className: "react-flow__controls-zoomout",
                title: "zoom out",
                "aria-label": "zoom out",
                disabled: b,
                children: c.jsx(oD, {})
            })]
        }), n && c.jsx(Ml, {
            className: "react-flow__controls-fitview",
            onClick: E,
            title: "fit view",
            "aria-label": "fit view",
            children: c.jsx(sD, {})
        }), r && c.jsx(Ml, {
            className: "react-flow__controls-interactive",
            onClick: T,
            title: "toggle interactivity",
            "aria-label": "toggle interactivity",
            children: m ? c.jsx(aD, {}) : c.jsx(iD, {})
        }), d]
    })
}

BC.displayName = "Controls";
g.memo(BC);

function cD({
                id: e,
                x: t,
                y: n,
                width: r,
                height: o,
                style: s,
                color: i,
                strokeColor: a,
                strokeWidth: l,
                className: u,
                borderRadius: d,
                shapeRendering: f,
                selected: p,
                onClick: h
            }) {
    const {background: x, backgroundColor: m} = s || {}, b = i || x || m;
    return c.jsx("rect", {
        className: ze(["react-flow__minimap-node", {selected: p}, u]),
        x: t,
        y: n,
        rx: d,
        ry: d,
        width: r,
        height: o,
        style: {fill: b, stroke: a, strokeWidth: l},
        shapeRendering: f,
        onClick: h ? y => h(y, e) : void 0
    })
}

const uD = g.memo(cD), dD = e => e.nodes.map(t => t.id), qd = e => e instanceof Function ? e : () => e;

function fD({
                nodeStrokeColor: e,
                nodeColor: t,
                nodeClassName: n = "",
                nodeBorderRadius: r = 5,
                nodeStrokeWidth: o,
                nodeComponent: s = uD,
                onClick: i
            }) {
    const a = me(dD, $e), l = qd(t), u = qd(e), d = qd(n),
        f = typeof window > "u" || window.chrome ? "crispEdges" : "geometricPrecision";
    return c.jsx(c.Fragment, {
        children: a.map(p => c.jsx(hD, {
            id: p,
            nodeColorFunc: l,
            nodeStrokeColorFunc: u,
            nodeClassNameFunc: d,
            nodeBorderRadius: r,
            nodeStrokeWidth: o,
            NodeComponent: s,
            onClick: i,
            shapeRendering: f
        }, p))
    })
}

function pD({
                id: e,
                nodeColorFunc: t,
                nodeStrokeColorFunc: n,
                nodeClassNameFunc: r,
                nodeBorderRadius: o,
                nodeStrokeWidth: s,
                shapeRendering: i,
                NodeComponent: a,
                onClick: l
            }) {
    const {node: u, x: d, y: f, width: p, height: h} = me(x => {
        const {internals: m} = x.nodeLookup.get(e), b = m.userNode, {x: y, y: w} = m.positionAbsolute, {
            width: v,
            height: C
        } = sr(b);
        return {node: b, x: y, y: w, width: v, height: C}
    }, $e);
    return !u || u.hidden || !$b(u) ? null : c.jsx(a, {
        x: d,
        y: f,
        width: p,
        height: h,
        style: u.style,
        selected: !!u.selected,
        className: r(u),
        color: t(u),
        borderRadius: o,
        strokeColor: n(u),
        strokeWidth: s,
        shapeRendering: i,
        onClick: l,
        id: u.id
    })
}

const hD = g.memo(pD);
var mD = g.memo(fD);
const gD = 200, vD = 150, yD = e => !e.hidden, xD = e => {
    const t = {
        x: -e.transform[0] / e.transform[2],
        y: -e.transform[1] / e.transform[2],
        width: e.width / e.transform[2],
        height: e.height / e.transform[2]
    };
    return {
        viewBB: t,
        boundingRect: e.nodeLookup.size > 0 ? Db(Oa(e.nodeLookup, {filter: yD}), t) : t,
        rfId: e.rfId,
        panZoom: e.panZoom,
        translateExtent: e.translateExtent,
        flowWidth: e.width,
        flowHeight: e.height
    }
}, wD = "react-flow__minimap-desc";

function VC({
                style: e,
                className: t,
                nodeStrokeColor: n,
                nodeColor: r,
                nodeClassName: o = "",
                nodeBorderRadius: s = 5,
                nodeStrokeWidth: i,
                nodeComponent: a,
                bgColor: l,
                maskColor: u,
                maskStrokeColor: d,
                maskStrokeWidth: f,
                position: p = "bottom-right",
                onClick: h,
                onNodeClick: x,
                pannable: m = !1,
                zoomable: b = !1,
                ariaLabel: y = "React Flow mini map",
                inversePan: w,
                zoomStep: v = 10,
                offsetScale: C = 5
            }) {
    const S = Te(), N = g.useRef(null), {
            boundingRect: E,
            viewBB: T,
            rfId: O,
            panZoom: D,
            translateExtent: L,
            flowWidth: $,
            flowHeight: B
        } = me(xD, $e), k = (e == null ? void 0 : e.width) ?? gD, M = (e == null ? void 0 : e.height) ?? vD,
        j = E.width / k, A = E.height / M, P = Math.max(j, A), _ = P * k, R = P * M, z = C * P,
        I = E.x - (_ - E.width) / 2 - z, V = E.y - (R - E.height) / 2 - z, H = _ + z * 2, U = R + z * 2,
        K = `${wD}-${O}`, F = g.useRef(0), Y = g.useRef();
    F.current = P, g.useEffect(() => {
        if (N.current && D) return Y.current = S3({
            domNode: N.current,
            panZoom: D,
            getTransform: () => S.getState().transform,
            getViewScale: () => F.current
        }), () => {
            var ee;
            (ee = Y.current) == null || ee.destroy()
        }
    }, [D]), g.useEffect(() => {
        var ee;
        (ee = Y.current) == null || ee.update({
            translateExtent: L,
            width: $,
            height: B,
            inversePan: w,
            pannable: m,
            zoomStep: v,
            zoomable: b
        })
    }, [m, b, w, v, L, $, B]);
    const X = h ? ee => {
        var ie;
        const [Q, ue] = ((ie = Y.current) == null ? void 0 : ie.pointer(ee)) || [0, 0];
        h(ee, {x: Q, y: ue})
    } : void 0, te = x ? g.useCallback((ee, Q) => {
        const ue = S.getState().nodeLookup.get(Q).internals.userNode;
        x(ee, ue)
    }, []) : void 0;
    return c.jsx($u, {
        position: p,
        style: {
            ...e,
            "--xy-minimap-background-color-props": typeof l == "string" ? l : void 0,
            "--xy-minimap-mask-background-color-props": typeof u == "string" ? u : void 0,
            "--xy-minimap-mask-stroke-color-props": typeof d == "string" ? d : void 0,
            "--xy-minimap-mask-stroke-width-props": typeof f == "number" ? f * P : void 0,
            "--xy-minimap-node-background-color-props": typeof r == "string" ? r : void 0,
            "--xy-minimap-node-stroke-color-props": typeof n == "string" ? n : void 0,
            "--xy-minimap-node-stroke-width-props": typeof i == "number" ? i : void 0
        },
        className: ze(["react-flow__minimap", t]),
        "data-testid": "rf__minimap",
        children: c.jsxs("svg", {
            width: k,
            height: M,
            viewBox: `${I} ${V} ${H} ${U}`,
            className: "react-flow__minimap-svg",
            role: "img",
            "aria-labelledby": K,
            ref: N,
            onClick: X,
            children: [y && c.jsx("title", {id: K, children: y}), c.jsx(mD, {
                onClick: te,
                nodeColor: r,
                nodeStrokeColor: n,
                nodeBorderRadius: s,
                nodeClassName: o,
                nodeStrokeWidth: i,
                nodeComponent: a
            }), c.jsx("path", {
                className: "react-flow__minimap-mask", d: `M${I - z},${V - z}h${H + z * 2}v${U + z * 2}h${-H - z * 2}z
        M${T.x},${T.y}h${T.width}v${T.height}h${-T.width}z`, fillRule: "evenodd", pointerEvents: "none"
            })]
        })
    })
}

VC.displayName = "MiniMap";
g.memo(VC);

function bD({
                nodeId: e,
                position: t,
                variant: n = Fi.Handle,
                className: r,
                style: o = {},
                children: s,
                color: i,
                minWidth: a = 10,
                minHeight: l = 10,
                maxWidth: u = Number.MAX_VALUE,
                maxHeight: d = Number.MAX_VALUE,
                keepAspectRatio: f = !1,
                resizeDirection: p,
                shouldResize: h,
                onResizeStart: x,
                onResize: m,
                onResizeEnd: b
            }) {
    const y = xC(), w = typeof e == "string" ? e : y, v = Te(), C = g.useRef(null),
        S = n === Fi.Line ? "right" : "bottom-right", N = t ?? S, E = g.useRef(null);
    g.useEffect(() => {
        if (!(!C.current || !w)) return E.current || (E.current = L3({
            domNode: C.current, nodeId: w, getStoreItems: () => {
                const {
                    nodeLookup: L,
                    transform: $,
                    snapGrid: B,
                    snapToGrid: k,
                    nodeOrigin: M,
                    domNode: j
                } = v.getState();
                return {nodeLookup: L, transform: $, snapGrid: B, snapToGrid: k, nodeOrigin: M, paneDomNode: j}
            }, onChange: (L, $) => {
                const {triggerNodeChanges: B, nodeLookup: k, parentLookup: M, nodeOrigin: j} = v.getState(), A = [],
                    P = {x: L.x, y: L.y}, _ = k.get(w);
                if (_ && _.expandParent && _.parentId) {
                    const R = _.origin ?? j, z = L.width ?? _.measured.width ?? 0,
                        I = L.height ?? _.measured.height ?? 0, V = {
                            id: _.id,
                            parentId: _.parentId,
                            rect: {
                                width: z, height: I, ...Lb({x: L.x ?? _.position.x, y: L.y ?? _.position.y}, {
                                    width: z,
                                    height: I
                                }, _.parentId, k, R)
                            }
                        }, H = gm([V], k, M, j);
                    A.push(...H), P.x = L.x ? Math.max(R[0] * z, L.x) : void 0, P.y = L.y ? Math.max(R[1] * I, L.y) : void 0
                }
                if (P.x !== void 0 && P.y !== void 0) {
                    const R = {id: w, type: "position", position: {...P}};
                    A.push(R)
                }
                if (L.width !== void 0 && L.height !== void 0) {
                    const z = {
                        id: w,
                        type: "dimensions",
                        resizing: !0,
                        setAttributes: p ? p === "horizontal" ? "width" : "height" : !0,
                        dimensions: {width: L.width, height: L.height}
                    };
                    A.push(z)
                }
                for (const R of $) {
                    const z = {...R, type: "position"};
                    A.push(z)
                }
                B(A)
            }, onEnd: ({width: L, height: $}) => {
                const B = {id: w, type: "dimensions", resizing: !1, dimensions: {width: L, height: $}};
                v.getState().triggerNodeChanges([B])
            }
        })), E.current.update({
            controlPosition: N,
            boundaries: {minWidth: a, minHeight: l, maxWidth: u, maxHeight: d},
            keepAspectRatio: f,
            resizeDirection: p,
            onResizeStart: x,
            onResize: m,
            onResizeEnd: b,
            shouldResize: h
        }), () => {
            var L;
            (L = E.current) == null || L.destroy()
        }
    }, [N, a, l, u, d, f, x, m, b, h]);
    const T = N.split("-"), O = n === Fi.Line ? "borderColor" : "backgroundColor", D = i ? {...o, [O]: i} : o;
    return c.jsx("div", {
        className: ze(["react-flow__resize-control", "nodrag", ...T, n, r]),
        ref: C,
        style: D,
        children: s
    })
}

g.memo(bD);
const CD = mu("inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0", {
    variants: {
        variant: {
            default: "bg-primary text-primary-foreground hover:bg-primary/90",
            destructive: "bg-destructive text-destructive-foreground hover:bg-destructive/90",
            outline: "border border-input bg-background hover:bg-accent hover:text-accent-foreground",
            secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
            ghost: "hover:bg-accent hover:text-accent-foreground",
            link: "text-primary underline-offset-4 hover:underline"
        }, size: {default: "h-10 px-4 py-2", sm: "h-9 rounded-md px-3", lg: "h-11 rounded-md px-8", icon: "h-10 w-10"}
    }, defaultVariants: {variant: "default", size: "default"}
}), le = g.forwardRef(({className: e, variant: t, size: n, asChild: r = !1, ...o}, s) => {
    const i = r ? Q_ : "button";
    return c.jsx(i, {className: se(CD({variant: t, size: n, className: e})), ref: s, ...o})
});
le.displayName = "Button";
const rt = () => {
    const {addNodes: e, deleteElements: t, getNodes: n, setViewport: r, getViewport: o, setNodes: s} = $t(), i = () => {
            const C = n();
            if (C.length === 0) return {x: 0, y: 100};
            const S = C.reduce((N, E) => N.position.x > E.position.x ? N : E);
            return {x: S.position.x + 400, y: S.position.y}
        }, a = g.useCallback((C, S) => {
            s(N => N.map(E => E.id === C ? {...E, data: {...E.data, ...S}} : E))
        }, [s]), l = g.useCallback((C, S, N) => {
            var B, k;
            const T = n().find(M => M.type === "webhook"),
                O = ((k = (B = T == null ? void 0 : T.data) == null ? void 0 : B.settings) == null ? void 0 : k.webhookVariables) || [],
                D = i(), L = {
                    id: `${C}-${Math.random()}`,
                    type: C === "incoming_message" ? "incomingMessage" : C === "end" ? "end" : C === "trigger" ? "trigger" : C === "keyword_trigger" ? "keyword_trigger" : C === "opening_hours" ? "opening_hours" : C === "template" ? "template" : C === "webhook" ? "webhook" : C === "wait" ? "wait" : C === "http" ? "http" : C === "question" ? "question" : C === "image" ? "image" : C === "pdf" ? "pdf" : C === "video" ? "video" : C === "message" ? "message" : C === "quick_replies" ? "quick_replies" : C === "openai" ? "openai" : C === "datastore" ? "datastore" : C === "branch" ? "branch" : "action",
                    position: D,
                    data: N || {
                        label: C === "incoming_message" ? "En mensaje" : C === "keyword_trigger" ? "On Keyword" : C === "wait" ? "Wait" : C === "http" ? "HTTP Request" : C === "question" ? "Question" : C === "image" ? "Image" : C === "pdf" ? "PDF" : C === "video" ? "Video" : C === "message" ? "Message" : C === "quick_replies" ? "Quick Replies Message" : C === "openai" ? "OpenAI" : C === "datastore" ? "Data Store" : C.charAt(0).toUpperCase() + C.slice(1),
                        type: C,
                        settings: C === "branch" ? {webhookVariables: O} : C === "wait" ? {
                            waitTime: 0,
                            waitUnit: "seconds"
                        } : C === "http" ? {method: "GET", url: "", headers: [], params: []} : C === "question" ? {
                            question: "",
                            variableName: ""
                        } : C === "quick_replies" ? {
                            header: "",
                            body: "",
                            footer: "",
                            activeButtons: 1,
                            button1: "",
                            button2: "",
                            button3: ""
                        } : C === "openai" ? {
                            openai: {
                                model: "gpt-4o-mini",
                                prompt: "",
                                systemPrompt: "",
                                temperature: .7,
                                maxTokens: 1e3,
                                responseHandling: "reply"
                            }
                        } : C === "datastore" ? {dataSource: ""} : {}
                    }
                };
            e(L);
            const $ = o();
            return r({
                x: -(D.x * $.zoom - window.innerWidth / 2),
                y: -(D.y * $.zoom - window.innerHeight / 2),
                zoom: $.zoom
            }, {duration: 800}), L
        }, [e, n, o, r]),
        u = g.useCallback(C => l("keyword_trigger", C, {keywords: [{id: "1", value: "", matchType: "exact"}]}), [l]),
        d = g.useCallback(C => l("quick_replies", C, {settings: {quickReplies: [], activeButtons: 1}}), [l]),
        f = g.useCallback((C, S) => l("message", C, {
            label: "Text Message",
            settings: {message: "", ...(S == null ? void 0 : S.settings) || {}}
        }), [l]), p = g.useCallback((C, S) => l("template", C, {
            label: "Template Message",
            type: "template",
            settings: {templateId: "", parameters: {}}
        }), [l]), h = g.useCallback(C => l("webhook", C, {settings: {webhookVariables: []}}), [l]),
        x = g.useCallback(C => l("incoming_message", C), [l]), m = g.useCallback(C => l("end", C), [l]),
        b = g.useCallback(C => l("openai", C, {
            label: "OpenAI",
            type: "openai",
            settings: {
                openai: {
                    model: "gpt-4o-mini",
                    prompt: "",
                    systemPrompt: "",
                    temperature: .7,
                    maxTokens: 1e3,
                    responseHandling: "reply",
                    variableName: ""
                }
            }
        }), [l]), y = g.useCallback(C => {
            t({nodes: [{id: C}]})
        }, [t]), w = g.useCallback(C => l("opening_hours", C, {
            settings: {
                timeRanges: [{
                    day: "Monday",
                    start: "09:00",
                    end: "17:00"
                }]
            }
        }), [l]), v = g.useCallback(C => l("http", C, {
            settings: {
                http: {
                    method: "GET",
                    url: "",
                    headers: [],
                    params: []
                }
            }
        }), [l]);
    return {
        createNodeBase: l,
        createNodeKeyword: u,
        createNodeQuickReply: d,
        createNodeMessage: f,
        createNodeTemplate: p,
        createNodeWebhook: h,
        createNodeIncomingMessage: x,
        createNodeEnd: m,
        createNodeOpeningHours: w,
        createNodeHTTP: v,
        createNodeOpenAI: b,
        deleteNode: y,
        updateNode: a
    }
}, SD = ({searchQuery: e}) => {
    const {createNodeIncomingMessage: t, createNodeKeyword: n} = rt(), o = [{
        icon: ja,
        label: "En mensaje",
        description: "Trigger on any incoming message",
        bgColor: "bg-green-100",
        textColor: "text-green-600",
        onClick: () => t({x: 0, y: 0})
    }, {
        icon: Xj,
        label: "Keyword",
        description: "Branch flow based on specific keywords",
        bgColor: "bg-blue-100",
        textColor: "text-blue-600",
        onClick: () => n({x: 0, y: 0})
    }].filter(s => s.label.toLowerCase().includes(e.toLowerCase()) || s.description.toLowerCase().includes(e.toLowerCase()));
    return o.length === 0 ? null : c.jsx("div", {
        className: "grid gap-2",
        children: o.map((s, i) => c.jsxs(le, {
            variant: "ghost",
            className: "w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none p-2",
            onClick: s.onClick,
            children: [c.jsx("div", {
                className: `${s.bgColor} p-2 rounded-lg mr-3`,
                children: c.jsx(s.icon, {className: `h-5 w-5 ${s.textColor}`})
            }), c.jsxs("div", {
                className: "text-left",
                children: [c.jsx("div", {children: s.label}), c.jsx("div", {
                    className: "text-xs text-gray-500 mt-1",
                    children: s.description
                })]
            })]
        }, i))
    })
}, ND = [{
    icon: Hh, label: "Send text message", bgColor: "bg-blue-100", textColor: "text-blue-600", onClick: e => {
        const t = {x: 250, y: 100}, n = {label: "Send Message", type: "message", settings: {message: ""}};
        return e.createNodeBase("message", t, n)
    }
}, {
    icon: Vh, label: "Send image", bgColor: "bg-purple-100", textColor: "text-purple-600", onClick: e => {
        const t = {x: 250, y: 100}, n = {label: "Send Image", type: "image", settings: {imageUrl: ""}};
        return e.createNodeBase("image", t, n)
    }
}, {
    icon: sp, label: "Send PDF", bgColor: "bg-red-100", textColor: "text-red-600", onClick: e => {
        const t = {x: 250, y: 100}, n = {label: "Send PDF", type: "pdf", settings: {pdfUrl: ""}};
        return e.createNodeBase("pdf", t, n)
    }
}, {
    icon: Gj, label: "Send video", bgColor: "bg-indigo-100", textColor: "text-indigo-600", onClick: e => {
        const t = {x: 250, y: 100}, n = {label: "Send Video", type: "video", settings: {videoUrl: ""}};
        return e.createNodeBase("video", t, n)
    }
}, {
    icon: bo,
    label: "Send template",
    bgColor: "bg-indigo-100",
    textColor: "text-indigo-600",
    onClick: e => e.createNodeTemplate({x: 250, y: 100})
}, {
    icon: ja, label: "Message with buttons", bgColor: "bg-orange-100", textColor: "text-orange-600", onClick: e => {
        const t = {x: 250, y: 100}, n = {
            label: "Quick Replies Message",
            type: "quick_replies",
            settings: {header: "", body: "", footer: "", button1: "", button2: "", button3: ""}
        };
        return e.createNodeBase("quick_replies", t, n)
    }
}], ED = ({searchQuery: e}) => {
    const t = rt(), n = ND.filter(r => r.label.toLowerCase().includes(e.toLowerCase()));
    return n.length === 0 ? null : c.jsx("div", {
        className: "grid gap-2",
        children: n.map((r, o) => c.jsxs(le, {
            variant: "ghost",
            className: "w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none",
            onClick: () => r.onClick(t),
            children: [c.jsx("div", {
                className: `${r.bgColor} p-2 rounded-lg mr-3`,
                children: c.jsx(r.icon, {className: `h-5 w-5 ${r.textColor}`})
            }), r.label]
        }, o))
    })
}, kD = ({searchQuery: e}) => {
    const {createNodeBase: t} = rt(), r = [{
        icon: n1,
        label: "Branch",
        bgColor: "bg-violet-100",
        textColor: "text-violet-600",
        onClick: () => t("branch", {x: 0, y: 0})
    }, {
        icon: o1,
        label: "End",
        bgColor: "bg-red-100",
        textColor: "text-red-600",
        onClick: () => t("end", {x: 0, y: 0}, {label: "End Flow"})
    }].filter(o => o.label.toLowerCase().includes(e.toLowerCase()));
    return r.length === 0 ? null : c.jsx("div", {
        className: "grid gap-2",
        children: r.map((o, s) => c.jsxs(le, {
            variant: "ghost",
            className: "w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none",
            onClick: o.onClick,
            children: [c.jsx("div", {
                className: `${o.bgColor} p-2 rounded-lg mr-3`,
                children: c.jsx(o.icon, {className: `h-5 w-5 ${o.textColor}`})
            }), o.label]
        }, s))
    })
}, _D = ({searchQuery: e}) => {
    const {createNodeOpenAI: t} = rt(), r = [{
        name: "OpenAI",
        icon: Zw,
        bgColor: "bg-black",
        textColor: "text-white",
        borderColor: "rainbow-border",
        onClick: () => t({x: 0, y: 0})
    }].filter(o => o.name.toLowerCase().includes(e.toLowerCase()));
    return c.jsx("div", {
        className: "grid gap-2",
        children: r.map(o => c.jsxs(le, {
            variant: "ghost",
            className: "w-full justify-start text-gray-200 hover:text-white focus:ring-0 focus-visible:ring-0 focus:outline-none group relative overflow-hidden",
            onClick: o.onClick,
            children: [c.jsxs("div", {
                className: `${o.bgColor} p-2 rounded-lg mr-3 relative overflow-hidden group-hover:animate-pulse rainbow-border`,
                children: [c.jsx(o.icon, {className: `h-5 w-5 ${o.textColor} relative z-10`}), c.jsx("div", {className: "absolute inset-0 bg-gradient-to-r from-ai-purple/30 via-ai-vivid/30 to-ai-magenta/30 opacity-0 group-hover:opacity-100 transition-opacity"})]
            }), c.jsxs("span", {
                className: "relative",
                children: [o.name, c.jsx(eP, {className: "absolute -top-1 -right-5 h-3 w-3 text-yellow-300 opacity-0 group-hover:opacity-100 transition-opacity"})]
            }), c.jsx("div", {className: "absolute inset-0 bg-gradient-to-r from-ai-purple/5 to-ai-magenta/5 opacity-0 group-hover:opacity-100 transition-opacity"})]
        }, o.name))
    })
}, jD = ({searchQuery: e, onOpenDataSidebar: t}) => {
    const {createNodeBase: n} = rt(), o = [{
        name: "Ask Question",
        icon: e1,
        bgColor: "bg-teal-100",
        textColor: "text-teal-600",
        onClick: () => n("question", {x: 0, y: 0}, {
            label: "Question",
            type: "question",
            settings: {question: "", variableName: ""}
        })
    }, {
        name: "Set Variable",
        icon: gu,
        bgColor: "bg-cyan-100",
        textColor: "text-cyan-600",
        onClick: () => n("datastore", {x: 0, y: 0}, {
            label: "Set Variable",
            type: "datastore",
            settings: {dataStore: {name: "", type: "database", connectionDetails: {}}}
        })
    }, {
        name: "AI Bot Training", icon: Qw, bgColor: "bg-purple-100", textColor: "text-purple-600", onClick: () => {
            t && t()
        }
    }].filter(s => s.name.toLowerCase().includes(e.toLowerCase()));
    return o.length === 0 ? null : c.jsx("div", {
        className: "grid gap-2",
        children: o.map(s => c.jsxs(le, {
            variant: "ghost",
            className: "w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none",
            onClick: s.onClick,
            children: [c.jsx("div", {
                className: `${s.bgColor} p-2 rounded-lg mr-3`,
                children: c.jsx(s.icon, {className: `h-5 w-5 ${s.textColor}`})
            }), s.name]
        }, s.name))
    })
}, PD = ({searchQuery: e}) => {
    const {createNodeWebhook: t, createNodeHTTP: n} = rt(), o = [{
        icon: i1,
        label: "Webhook",
        bgColor: "bg-purple-100",
        textColor: "text-purple-600",
        onClick: () => t({x: 0, y: 0})
    }, {
        icon: ra,
        label: "HTTP Request",
        bgColor: "bg-sky-100",
        textColor: "text-sky-600",
        onClick: () => n({x: 0, y: 0})
    }].filter(s => s.label.toLowerCase().includes(e.toLowerCase()));
    return o.length === 0 ? null : c.jsx("div", {
        className: "grid gap-2",
        children: o.map((s, i) => c.jsxs(le, {
            variant: "ghost",
            className: "w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none",
            onClick: s.onClick,
            children: [c.jsx("div", {
                className: `${s.bgColor} p-2 rounded-lg mr-3`,
                children: c.jsx(s.icon, {className: `h-5 w-5 ${s.textColor}`})
            }), s.label]
        }, i))
    })
};
var Qd = 0;

function zu() {
    g.useEffect(() => {
        const e = document.querySelectorAll("[data-radix-focus-guard]");
        return document.body.insertAdjacentElement("afterbegin", e[0] ?? O0()), document.body.insertAdjacentElement("beforeend", e[1] ?? O0()), Qd++, () => {
            Qd === 1 && document.querySelectorAll("[data-radix-focus-guard]").forEach(t => t.remove()), Qd--
        }
    }, [])
}

function O0() {
    const e = document.createElement("span");
    return e.setAttribute("data-radix-focus-guard", ""), e.tabIndex = 0, e.style.outline = "none", e.style.opacity = "0", e.style.position = "fixed", e.style.pointerEvents = "none", e
}

var Zd = "focusScope.autoFocusOnMount", Jd = "focusScope.autoFocusOnUnmount", D0 = {bubbles: !1, cancelable: !0},
    TD = "FocusScope", $a = g.forwardRef((e, t) => {
        const {
            loop: n = !1,
            trapped: r = !1,
            onMountAutoFocus: o,
            onUnmountAutoFocus: s,
            ...i
        } = e, [a, l] = g.useState(null), u = Xe(o), d = Xe(s), f = g.useRef(null), p = ge(t, m => l(m)), h = g.useRef({
            paused: !1, pause() {
                this.paused = !0
            }, resume() {
                this.paused = !1
            }
        }).current;
        g.useEffect(() => {
            if (r) {
                let m = function (v) {
                    if (h.paused || !a) return;
                    const C = v.target;
                    a.contains(C) ? f.current = C : wr(f.current, {select: !0})
                }, b = function (v) {
                    if (h.paused || !a) return;
                    const C = v.relatedTarget;
                    C !== null && (a.contains(C) || wr(f.current, {select: !0}))
                }, y = function (v) {
                    if (document.activeElement === document.body) for (const S of v) S.removedNodes.length > 0 && wr(a)
                };
                document.addEventListener("focusin", m), document.addEventListener("focusout", b);
                const w = new MutationObserver(y);
                return a && w.observe(a, {childList: !0, subtree: !0}), () => {
                    document.removeEventListener("focusin", m), document.removeEventListener("focusout", b), w.disconnect()
                }
            }
        }, [r, a, h.paused]), g.useEffect(() => {
            if (a) {
                L0.add(h);
                const m = document.activeElement;
                if (!a.contains(m)) {
                    const y = new CustomEvent(Zd, D0);
                    a.addEventListener(Zd, u), a.dispatchEvent(y), y.defaultPrevented || (MD(DD(HC(a)), {select: !0}), document.activeElement === m && wr(a))
                }
                return () => {
                    a.removeEventListener(Zd, u), setTimeout(() => {
                        const y = new CustomEvent(Jd, D0);
                        a.addEventListener(Jd, d), a.dispatchEvent(y), y.defaultPrevented || wr(m ?? document.body, {select: !0}), a.removeEventListener(Jd, d), L0.remove(h)
                    }, 0)
                }
            }
        }, [a, u, d, h]);
        const x = g.useCallback(m => {
            if (!n && !r || h.paused) return;
            const b = m.key === "Tab" && !m.altKey && !m.ctrlKey && !m.metaKey, y = document.activeElement;
            if (b && y) {
                const w = m.currentTarget, [v, C] = RD(w);
                v && C ? !m.shiftKey && y === C ? (m.preventDefault(), n && wr(v, {select: !0})) : m.shiftKey && y === v && (m.preventDefault(), n && wr(C, {select: !0})) : y === w && m.preventDefault()
            }
        }, [n, r, h.paused]);
        return c.jsx(re.div, {tabIndex: -1, ...i, ref: p, onKeyDown: x})
    });
$a.displayName = TD;

function MD(e, {select: t = !1} = {}) {
    const n = document.activeElement;
    for (const r of e) if (wr(r, {select: t}), document.activeElement !== n) return
}

function RD(e) {
    const t = HC(e), n = $0(t, e), r = $0(t.reverse(), e);
    return [n, r]
}

function HC(e) {
    const t = [], n = document.createTreeWalker(e, NodeFilter.SHOW_ELEMENT, {
        acceptNode: r => {
            const o = r.tagName === "INPUT" && r.type === "hidden";
            return r.disabled || r.hidden || o ? NodeFilter.FILTER_SKIP : r.tabIndex >= 0 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP
        }
    });
    for (; n.nextNode();) t.push(n.currentNode);
    return t
}

function $0(e, t) {
    for (const n of e) if (!AD(n, {upTo: t})) return n
}

function AD(e, {upTo: t}) {
    if (getComputedStyle(e).visibility === "hidden") return !0;
    for (; e;) {
        if (t !== void 0 && e === t) return !1;
        if (getComputedStyle(e).display === "none") return !0;
        e = e.parentElement
    }
    return !1
}

function ID(e) {
    return e instanceof HTMLInputElement && "select" in e
}

function wr(e, {select: t = !1} = {}) {
    if (e && e.focus) {
        const n = document.activeElement;
        e.focus({preventScroll: !0}), e !== n && ID(e) && t && e.select()
    }
}

var L0 = OD();

function OD() {
    let e = [];
    return {
        add(t) {
            const n = e[0];
            t !== n && (n == null || n.pause()), e = F0(e, t), e.unshift(t)
        }, remove(t) {
            var n;
            e = F0(e, t), (n = e[0]) == null || n.resume()
        }
    }
}

function F0(e, t) {
    const n = [...e], r = n.indexOf(t);
    return r !== -1 && n.splice(r, 1), n
}

function DD(e) {
    return e.filter(t => t.tagName !== "A")
}

var $D = function (e) {
    if (typeof document > "u") return null;
    var t = Array.isArray(e) ? e[0] : e;
    return t.ownerDocument.body
}, Ko = new WeakMap, Rl = new WeakMap, Al = {}, ef = 0, UC = function (e) {
    return e && (e.host || UC(e.parentNode))
}, LD = function (e, t) {
    return t.map(function (n) {
        if (e.contains(n)) return n;
        var r = UC(n);
        return r && e.contains(r) ? r : (console.error("aria-hidden", n, "in not contained inside", e, ". Doing nothing"), null)
    }).filter(function (n) {
        return !!n
    })
}, FD = function (e, t, n, r) {
    var o = LD(t, Array.isArray(e) ? e : [e]);
    Al[n] || (Al[n] = new WeakMap);
    var s = Al[n], i = [], a = new Set, l = new Set(o), u = function (f) {
        !f || a.has(f) || (a.add(f), u(f.parentNode))
    };
    o.forEach(u);
    var d = function (f) {
        !f || l.has(f) || Array.prototype.forEach.call(f.children, function (p) {
            if (a.has(p)) d(p); else try {
                var h = p.getAttribute(r), x = h !== null && h !== "false", m = (Ko.get(p) || 0) + 1,
                    b = (s.get(p) || 0) + 1;
                Ko.set(p, m), s.set(p, b), i.push(p), m === 1 && x && Rl.set(p, !0), b === 1 && p.setAttribute(n, "true"), x || p.setAttribute(r, "true")
            } catch (y) {
                console.error("aria-hidden: cannot operate on ", p, y)
            }
        })
    };
    return d(t), a.clear(), ef++, function () {
        i.forEach(function (f) {
            var p = Ko.get(f) - 1, h = s.get(f) - 1;
            Ko.set(f, p), s.set(f, h), p || (Rl.has(f) || f.removeAttribute(r), Rl.delete(f)), h || f.removeAttribute(n)
        }), ef--, ef || (Ko = new WeakMap, Ko = new WeakMap, Rl = new WeakMap, Al = {})
    }
}, Bu = function (e, t, n) {
    n === void 0 && (n = "data-aria-hidden");
    var r = Array.from(Array.isArray(e) ? e : [e]), o = $D(e);
    return o ? (r.push.apply(r, Array.from(o.querySelectorAll("[aria-live]"))), FD(r, o, n, "aria-hidden")) : function () {
        return null
    }
}, jn = function () {
    return jn = Object.assign || function (t) {
        for (var n, r = 1, o = arguments.length; r < o; r++) {
            n = arguments[r];
            for (var s in n) Object.prototype.hasOwnProperty.call(n, s) && (t[s] = n[s])
        }
        return t
    }, jn.apply(this, arguments)
};

function WC(e, t) {
    var n = {};
    for (var r in e) Object.prototype.hasOwnProperty.call(e, r) && t.indexOf(r) < 0 && (n[r] = e[r]);
    if (e != null && typeof Object.getOwnPropertySymbols == "function") for (var o = 0, r = Object.getOwnPropertySymbols(e); o < r.length; o++) t.indexOf(r[o]) < 0 && Object.prototype.propertyIsEnumerable.call(e, r[o]) && (n[r[o]] = e[r[o]]);
    return n
}

function zD(e, t, n) {
    if (n || arguments.length === 2) for (var r = 0, o = t.length, s; r < o; r++) (s || !(r in t)) && (s || (s = Array.prototype.slice.call(t, 0, r)), s[r] = t[r]);
    return e.concat(s || Array.prototype.slice.call(t))
}

var nc = "right-scroll-bar-position", rc = "width-before-scroll-bar", BD = "with-scroll-bars-hidden",
    VD = "--removed-body-scroll-bar-size";

function tf(e, t) {
    return typeof e == "function" ? e(t) : e && (e.current = t), e
}

function HD(e, t) {
    var n = g.useState(function () {
        return {
            value: e, callback: t, facade: {
                get current() {
                    return n.value
                }, set current(r) {
                    var o = n.value;
                    o !== r && (n.value = r, n.callback(r, o))
                }
            }
        }
    })[0];
    return n.callback = t, n.facade
}

var UD = typeof window < "u" ? g.useLayoutEffect : g.useEffect, z0 = new WeakMap;

function WD(e, t) {
    var n = HD(null, function (r) {
        return e.forEach(function (o) {
            return tf(o, r)
        })
    });
    return UD(function () {
        var r = z0.get(n);
        if (r) {
            var o = new Set(r), s = new Set(e), i = n.current;
            o.forEach(function (a) {
                s.has(a) || tf(a, null)
            }), s.forEach(function (a) {
                o.has(a) || tf(a, i)
            })
        }
        z0.set(n, e)
    }, [e]), n
}

function KD(e) {
    return e
}

function YD(e, t) {
    t === void 0 && (t = KD);
    var n = [], r = !1, o = {
        read: function () {
            if (r) throw new Error("Sidecar: could not `read` from an `assigned` medium. `read` could be used only with `useMedium`.");
            return n.length ? n[n.length - 1] : e
        }, useMedium: function (s) {
            var i = t(s, r);
            return n.push(i), function () {
                n = n.filter(function (a) {
                    return a !== i
                })
            }
        }, assignSyncMedium: function (s) {
            for (r = !0; n.length;) {
                var i = n;
                n = [], i.forEach(s)
            }
            n = {
                push: function (a) {
                    return s(a)
                }, filter: function () {
                    return n
                }
            }
        }, assignMedium: function (s) {
            r = !0;
            var i = [];
            if (n.length) {
                var a = n;
                n = [], a.forEach(s), i = n
            }
            var l = function () {
                var d = i;
                i = [], d.forEach(s)
            }, u = function () {
                return Promise.resolve().then(l)
            };
            u(), n = {
                push: function (d) {
                    i.push(d), u()
                }, filter: function (d) {
                    return i = i.filter(d), n
                }
            }
        }
    };
    return o
}

function GD(e) {
    e === void 0 && (e = {});
    var t = YD(null);
    return t.options = jn({async: !0, ssr: !1}, e), t
}

var KC = function (e) {
    var t = e.sideCar, n = WC(e, ["sideCar"]);
    if (!t) throw new Error("Sidecar: please provide `sideCar` property to import the right car");
    var r = t.read();
    if (!r) throw new Error("Sidecar medium not found");
    return g.createElement(r, jn({}, n))
};
KC.isSideCarExport = !0;

function XD(e, t) {
    return e.useMedium(t), KC
}

var YC = GD(), nf = function () {
}, Vu = g.forwardRef(function (e, t) {
    var n = g.useRef(null), r = g.useState({onScrollCapture: nf, onWheelCapture: nf, onTouchMoveCapture: nf}), o = r[0],
        s = r[1], i = e.forwardProps, a = e.children, l = e.className, u = e.removeScrollBar, d = e.enabled,
        f = e.shards, p = e.sideCar, h = e.noIsolation, x = e.inert, m = e.allowPinchZoom, b = e.as,
        y = b === void 0 ? "div" : b, w = e.gapMode,
        v = WC(e, ["forwardProps", "children", "className", "removeScrollBar", "enabled", "shards", "sideCar", "noIsolation", "inert", "allowPinchZoom", "as", "gapMode"]),
        C = p, S = WD([n, t]), N = jn(jn({}, v), o);
    return g.createElement(g.Fragment, null, d && g.createElement(C, {
        sideCar: YC,
        removeScrollBar: u,
        shards: f,
        noIsolation: h,
        inert: x,
        setCallbacks: s,
        allowPinchZoom: !!m,
        lockRef: n,
        gapMode: w
    }), i ? g.cloneElement(g.Children.only(a), jn(jn({}, N), {ref: S})) : g.createElement(y, jn({}, N, {
        className: l,
        ref: S
    }), a))
});
Vu.defaultProps = {enabled: !0, removeScrollBar: !0, inert: !1};
Vu.classNames = {fullWidth: rc, zeroRight: nc};
var qD = function () {
    if (typeof __webpack_nonce__ < "u") return __webpack_nonce__
};

function QD() {
    if (!document) return null;
    var e = document.createElement("style");
    e.type = "text/css";
    var t = qD();
    return t && e.setAttribute("nonce", t), e
}

function ZD(e, t) {
    e.styleSheet ? e.styleSheet.cssText = t : e.appendChild(document.createTextNode(t))
}

function JD(e) {
    var t = document.head || document.getElementsByTagName("head")[0];
    t.appendChild(e)
}

var e$ = function () {
    var e = 0, t = null;
    return {
        add: function (n) {
            e == 0 && (t = QD()) && (ZD(t, n), JD(t)), e++
        }, remove: function () {
            e--, !e && t && (t.parentNode && t.parentNode.removeChild(t), t = null)
        }
    }
}, t$ = function () {
    var e = e$();
    return function (t, n) {
        g.useEffect(function () {
            return e.add(t), function () {
                e.remove()
            }
        }, [t && n])
    }
}, GC = function () {
    var e = t$(), t = function (n) {
        var r = n.styles, o = n.dynamic;
        return e(r, o), null
    };
    return t
}, n$ = {left: 0, top: 0, right: 0, gap: 0}, rf = function (e) {
    return parseInt(e || "", 10) || 0
}, r$ = function (e) {
    var t = window.getComputedStyle(document.body), n = t[e === "padding" ? "paddingLeft" : "marginLeft"],
        r = t[e === "padding" ? "paddingTop" : "marginTop"], o = t[e === "padding" ? "paddingRight" : "marginRight"];
    return [rf(n), rf(r), rf(o)]
}, o$ = function (e) {
    if (e === void 0 && (e = "margin"), typeof window > "u") return n$;
    var t = r$(e), n = document.documentElement.clientWidth, r = window.innerWidth;
    return {left: t[0], top: t[1], right: t[2], gap: Math.max(0, r - n + t[2] - t[0])}
}, s$ = GC(), bs = "data-scroll-locked", i$ = function (e, t, n, r) {
    var o = e.left, s = e.top, i = e.right, a = e.gap;
    return n === void 0 && (n = "margin"), `
  .`.concat(BD, ` {
   overflow: hidden `).concat(r, `;
   padding-right: `).concat(a, "px ").concat(r, `;
  }
  body[`).concat(bs, `] {
    overflow: hidden `).concat(r, `;
    overscroll-behavior: contain;
    `).concat([t && "position: relative ".concat(r, ";"), n === "margin" && `
    padding-left: `.concat(o, `px;
    padding-top: `).concat(s, `px;
    padding-right: `).concat(i, `px;
    margin-left:0;
    margin-top:0;
    margin-right: `).concat(a, "px ").concat(r, `;
    `), n === "padding" && "padding-right: ".concat(a, "px ").concat(r, ";")].filter(Boolean).join(""), `
  }
  
  .`).concat(nc, ` {
    right: `).concat(a, "px ").concat(r, `;
  }
  
  .`).concat(rc, ` {
    margin-right: `).concat(a, "px ").concat(r, `;
  }
  
  .`).concat(nc, " .").concat(nc, ` {
    right: 0 `).concat(r, `;
  }
  
  .`).concat(rc, " .").concat(rc, ` {
    margin-right: 0 `).concat(r, `;
  }
  
  body[`).concat(bs, `] {
    `).concat(VD, ": ").concat(a, `px;
  }
`)
}, B0 = function () {
    var e = parseInt(document.body.getAttribute(bs) || "0", 10);
    return isFinite(e) ? e : 0
}, a$ = function () {
    g.useEffect(function () {
        return document.body.setAttribute(bs, (B0() + 1).toString()), function () {
            var e = B0() - 1;
            e <= 0 ? document.body.removeAttribute(bs) : document.body.setAttribute(bs, e.toString())
        }
    }, [])
}, l$ = function (e) {
    var t = e.noRelative, n = e.noImportant, r = e.gapMode, o = r === void 0 ? "margin" : r;
    a$();
    var s = g.useMemo(function () {
        return o$(o)
    }, [o]);
    return g.createElement(s$, {styles: i$(s, !t, o, n ? "" : "!important")})
}, _p = !1;
if (typeof window < "u") try {
    var Il = Object.defineProperty({}, "passive", {
        get: function () {
            return _p = !0, !0
        }
    });
    window.addEventListener("test", Il, Il), window.removeEventListener("test", Il, Il)
} catch {
    _p = !1
}
var Yo = _p ? {passive: !1} : !1, c$ = function (e) {
    return e.tagName === "TEXTAREA"
}, XC = function (e, t) {
    if (!(e instanceof Element)) return !1;
    var n = window.getComputedStyle(e);
    return n[t] !== "hidden" && !(n.overflowY === n.overflowX && !c$(e) && n[t] === "visible")
}, u$ = function (e) {
    return XC(e, "overflowY")
}, d$ = function (e) {
    return XC(e, "overflowX")
}, V0 = function (e, t) {
    var n = t.ownerDocument, r = t;
    do {
        typeof ShadowRoot < "u" && r instanceof ShadowRoot && (r = r.host);
        var o = qC(e, r);
        if (o) {
            var s = QC(e, r), i = s[1], a = s[2];
            if (i > a) return !0
        }
        r = r.parentNode
    } while (r && r !== n.body);
    return !1
}, f$ = function (e) {
    var t = e.scrollTop, n = e.scrollHeight, r = e.clientHeight;
    return [t, n, r]
}, p$ = function (e) {
    var t = e.scrollLeft, n = e.scrollWidth, r = e.clientWidth;
    return [t, n, r]
}, qC = function (e, t) {
    return e === "v" ? u$(t) : d$(t)
}, QC = function (e, t) {
    return e === "v" ? f$(t) : p$(t)
}, h$ = function (e, t) {
    return e === "h" && t === "rtl" ? -1 : 1
}, m$ = function (e, t, n, r, o) {
    var s = h$(e, window.getComputedStyle(t).direction), i = s * r, a = n.target, l = t.contains(a), u = !1, d = i > 0,
        f = 0, p = 0;
    do {
        var h = QC(e, a), x = h[0], m = h[1], b = h[2], y = m - b - s * x;
        (x || y) && qC(e, a) && (f += y, p += x), a instanceof ShadowRoot ? a = a.host : a = a.parentNode
    } while (!l && a !== document.body || l && (t.contains(a) || t === a));
    return (d && Math.abs(f) < 1 || !d && Math.abs(p) < 1) && (u = !0), u
}, Ol = function (e) {
    return "changedTouches" in e ? [e.changedTouches[0].clientX, e.changedTouches[0].clientY] : [0, 0]
}, H0 = function (e) {
    return [e.deltaX, e.deltaY]
}, U0 = function (e) {
    return e && "current" in e ? e.current : e
}, g$ = function (e, t) {
    return e[0] === t[0] && e[1] === t[1]
}, v$ = function (e) {
    return `
  .block-interactivity-`.concat(e, ` {pointer-events: none;}
  .allow-interactivity-`).concat(e, ` {pointer-events: all;}
`)
}, y$ = 0, Go = [];

function x$(e) {
    var t = g.useRef([]), n = g.useRef([0, 0]), r = g.useRef(), o = g.useState(y$++)[0], s = g.useState(GC)[0],
        i = g.useRef(e);
    g.useEffect(function () {
        i.current = e
    }, [e]), g.useEffect(function () {
        if (e.inert) {
            document.body.classList.add("block-interactivity-".concat(o));
            var m = zD([e.lockRef.current], (e.shards || []).map(U0), !0).filter(Boolean);
            return m.forEach(function (b) {
                return b.classList.add("allow-interactivity-".concat(o))
            }), function () {
                document.body.classList.remove("block-interactivity-".concat(o)), m.forEach(function (b) {
                    return b.classList.remove("allow-interactivity-".concat(o))
                })
            }
        }
    }, [e.inert, e.lockRef.current, e.shards]);
    var a = g.useCallback(function (m, b) {
        if ("touches" in m && m.touches.length === 2 || m.type === "wheel" && m.ctrlKey) return !i.current.allowPinchZoom;
        var y = Ol(m), w = n.current, v = "deltaX" in m ? m.deltaX : w[0] - y[0],
            C = "deltaY" in m ? m.deltaY : w[1] - y[1], S, N = m.target, E = Math.abs(v) > Math.abs(C) ? "h" : "v";
        if ("touches" in m && E === "h" && N.type === "range") return !1;
        var T = V0(E, N);
        if (!T) return !0;
        if (T ? S = E : (S = E === "v" ? "h" : "v", T = V0(E, N)), !T) return !1;
        if (!r.current && "changedTouches" in m && (v || C) && (r.current = S), !S) return !0;
        var O = r.current || S;
        return m$(O, b, m, O === "h" ? v : C)
    }, []), l = g.useCallback(function (m) {
        var b = m;
        if (!(!Go.length || Go[Go.length - 1] !== s)) {
            var y = "deltaY" in b ? H0(b) : Ol(b), w = t.current.filter(function (S) {
                return S.name === b.type && (S.target === b.target || b.target === S.shadowParent) && g$(S.delta, y)
            })[0];
            if (w && w.should) {
                b.cancelable && b.preventDefault();
                return
            }
            if (!w) {
                var v = (i.current.shards || []).map(U0).filter(Boolean).filter(function (S) {
                    return S.contains(b.target)
                }), C = v.length > 0 ? a(b, v[0]) : !i.current.noIsolation;
                C && b.cancelable && b.preventDefault()
            }
        }
    }, []), u = g.useCallback(function (m, b, y, w) {
        var v = {name: m, delta: b, target: y, should: w, shadowParent: w$(y)};
        t.current.push(v), setTimeout(function () {
            t.current = t.current.filter(function (C) {
                return C !== v
            })
        }, 1)
    }, []), d = g.useCallback(function (m) {
        n.current = Ol(m), r.current = void 0
    }, []), f = g.useCallback(function (m) {
        u(m.type, H0(m), m.target, a(m, e.lockRef.current))
    }, []), p = g.useCallback(function (m) {
        u(m.type, Ol(m), m.target, a(m, e.lockRef.current))
    }, []);
    g.useEffect(function () {
        return Go.push(s), e.setCallbacks({
            onScrollCapture: f,
            onWheelCapture: f,
            onTouchMoveCapture: p
        }), document.addEventListener("wheel", l, Yo), document.addEventListener("touchmove", l, Yo), document.addEventListener("touchstart", d, Yo), function () {
            Go = Go.filter(function (m) {
                return m !== s
            }), document.removeEventListener("wheel", l, Yo), document.removeEventListener("touchmove", l, Yo), document.removeEventListener("touchstart", d, Yo)
        }
    }, []);
    var h = e.removeScrollBar, x = e.inert;
    return g.createElement(g.Fragment, null, x ? g.createElement(s, {styles: v$(o)}) : null, h ? g.createElement(l$, {gapMode: e.gapMode}) : null)
}

function w$(e) {
    for (var t = null; e !== null;) e instanceof ShadowRoot && (t = e.host, e = e.host), e = e.parentNode;
    return t
}

const b$ = XD(YC, x$);
var La = g.forwardRef(function (e, t) {
    return g.createElement(Vu, jn({}, e, {ref: t, sideCar: b$}))
});
La.classNames = Vu.classNames;
var ym = "Popover", [ZC, bz] = mn(ym, [eo]), Fa = eo(), [C$, to] = ZC(ym), JC = e => {
    const {__scopePopover: t, children: n, open: r, defaultOpen: o, onOpenChange: s, modal: i = !1} = e, a = Fa(t),
        l = g.useRef(null), [u, d] = g.useState(!1), [f = !1, p] = To({prop: r, defaultProp: o, onChange: s});
    return c.jsx(em, {
        ...a,
        children: c.jsx(C$, {
            scope: t,
            contentId: ct(),
            triggerRef: l,
            open: f,
            onOpenChange: p,
            onOpenToggle: g.useCallback(() => p(h => !h), [p]),
            hasCustomAnchor: u,
            onCustomAnchorAdd: g.useCallback(() => d(!0), []),
            onCustomAnchorRemove: g.useCallback(() => d(!1), []),
            modal: i,
            children: n
        })
    })
};
JC.displayName = ym;
var eS = "PopoverAnchor", S$ = g.forwardRef((e, t) => {
    const {__scopePopover: n, ...r} = e, o = to(eS, n), s = Fa(n), {onCustomAnchorAdd: i, onCustomAnchorRemove: a} = o;
    return g.useEffect(() => (i(), () => a()), [i, a]), c.jsx(Ma, {...s, ...r, ref: t})
});
S$.displayName = eS;
var tS = "PopoverTrigger", nS = g.forwardRef((e, t) => {
    const {__scopePopover: n, ...r} = e, o = to(tS, n), s = Fa(n), i = ge(t, o.triggerRef), a = c.jsx(re.button, {
        type: "button",
        "aria-haspopup": "dialog",
        "aria-expanded": o.open,
        "aria-controls": o.contentId,
        "data-state": aS(o.open), ...r,
        ref: i,
        onClick: J(e.onClick, o.onOpenToggle)
    });
    return o.hasCustomAnchor ? a : c.jsx(Ma, {asChild: !0, ...s, children: a})
});
nS.displayName = tS;
var xm = "PopoverPortal", [N$, E$] = ZC(xm, {forceMount: void 0}), rS = e => {
    const {__scopePopover: t, forceMount: n, children: r, container: o} = e, s = to(xm, t);
    return c.jsx(N$, {
        scope: t,
        forceMount: n,
        children: c.jsx(Dt, {present: n || s.open, children: c.jsx(Qs, {asChild: !0, container: o, children: r})})
    })
};
rS.displayName = xm;
var Ys = "PopoverContent", oS = g.forwardRef((e, t) => {
    const n = E$(Ys, e.__scopePopover), {forceMount: r = n.forceMount, ...o} = e, s = to(Ys, e.__scopePopover);
    return c.jsx(Dt, {present: r || s.open, children: s.modal ? c.jsx(_$, {...o, ref: t}) : c.jsx(j$, {...o, ref: t})})
});
oS.displayName = Ys;
var k$ = Kr("PopoverContent.RemoveScroll"), _$ = g.forwardRef((e, t) => {
    const n = to(Ys, e.__scopePopover), r = g.useRef(null), o = ge(t, r), s = g.useRef(!1);
    return g.useEffect(() => {
        const i = r.current;
        if (i) return Bu(i)
    }, []), c.jsx(La, {
        as: k$,
        allowPinchZoom: !0,
        children: c.jsx(sS, {
            ...e,
            ref: o,
            trapFocus: n.open,
            disableOutsidePointerEvents: !0,
            onCloseAutoFocus: J(e.onCloseAutoFocus, i => {
                var a;
                i.preventDefault(), s.current || (a = n.triggerRef.current) == null || a.focus()
            }),
            onPointerDownOutside: J(e.onPointerDownOutside, i => {
                const a = i.detail.originalEvent, l = a.button === 0 && a.ctrlKey === !0, u = a.button === 2 || l;
                s.current = u
            }, {checkForDefaultPrevented: !1}),
            onFocusOutside: J(e.onFocusOutside, i => i.preventDefault(), {checkForDefaultPrevented: !1})
        })
    })
}), j$ = g.forwardRef((e, t) => {
    const n = to(Ys, e.__scopePopover), r = g.useRef(!1), o = g.useRef(!1);
    return c.jsx(sS, {
        ...e, ref: t, trapFocus: !1, disableOutsidePointerEvents: !1, onCloseAutoFocus: s => {
            var i, a;
            (i = e.onCloseAutoFocus) == null || i.call(e, s), s.defaultPrevented || (r.current || (a = n.triggerRef.current) == null || a.focus(), s.preventDefault()), r.current = !1, o.current = !1
        }, onInteractOutside: s => {
            var l, u;
            (l = e.onInteractOutside) == null || l.call(e, s), s.defaultPrevented || (r.current = !0, s.detail.originalEvent.type === "pointerdown" && (o.current = !0));
            const i = s.target;
            ((u = n.triggerRef.current) == null ? void 0 : u.contains(i)) && s.preventDefault(), s.detail.originalEvent.type === "focusin" && o.current && s.preventDefault()
        }
    })
}), sS = g.forwardRef((e, t) => {
    const {
        __scopePopover: n,
        trapFocus: r,
        onOpenAutoFocus: o,
        onCloseAutoFocus: s,
        disableOutsidePointerEvents: i,
        onEscapeKeyDown: a,
        onPointerDownOutside: l,
        onFocusOutside: u,
        onInteractOutside: d,
        ...f
    } = e, p = to(Ys, n), h = Fa(n);
    return zu(), c.jsx($a, {
        asChild: !0,
        loop: !0,
        trapped: r,
        onMountAutoFocus: o,
        onUnmountAutoFocus: s,
        children: c.jsx(zo, {
            asChild: !0,
            disableOutsidePointerEvents: i,
            onInteractOutside: d,
            onEscapeKeyDown: a,
            onPointerDownOutside: l,
            onFocusOutside: u,
            onDismiss: () => p.onOpenChange(!1),
            children: c.jsx(wu, {
                "data-state": aS(p.open),
                role: "dialog",
                id: p.contentId, ...h, ...f,
                ref: t,
                style: {
                    ...f.style,
                    "--radix-popover-content-transform-origin": "var(--radix-popper-transform-origin)",
                    "--radix-popover-content-available-width": "var(--radix-popper-available-width)",
                    "--radix-popover-content-available-height": "var(--radix-popper-available-height)",
                    "--radix-popover-trigger-width": "var(--radix-popper-anchor-width)",
                    "--radix-popover-trigger-height": "var(--radix-popper-anchor-height)"
                }
            })
        })
    })
}), iS = "PopoverClose", P$ = g.forwardRef((e, t) => {
    const {__scopePopover: n, ...r} = e, o = to(iS, n);
    return c.jsx(re.button, {type: "button", ...r, ref: t, onClick: J(e.onClick, () => o.onOpenChange(!1))})
});
P$.displayName = iS;
var T$ = "PopoverArrow", M$ = g.forwardRef((e, t) => {
    const {__scopePopover: n, ...r} = e, o = Fa(n);
    return c.jsx(bu, {...o, ...r, ref: t})
});
M$.displayName = T$;

function aS(e) {
    return e ? "open" : "closed"
}

var R$ = JC, A$ = nS, I$ = rS, lS = oS;
const W0 = R$, K0 = A$, jp = g.forwardRef(({
                                               className: e,
                                               align: t = "center",
                                               sideOffset: n = 4,
                                               ...r
                                           }, o) => c.jsx(I$, {
    children: c.jsx(lS, {
        ref: o,
        align: t,
        sideOffset: n,
        className: se("z-50 w-72 rounded-md border bg-popover p-4 text-popover-foreground shadow-md outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2", e), ...r
    })
}));
jp.displayName = lS.displayName;
const O$ = ({onImportClick: e, onPopoverIndexChange: t, onOpenDataSidebar: n}) => {
    const [r, o] = g.useState(""), [s, i] = g.useState(null), a = x => {
        i(x), t && t(x)
    }, l = x => {
        a(x)
    }, u = () => {
        a(null)
    }, d = () => {
        n()
    }, f = [{icon: tP, label: "Events", content: c.jsx(SD, {searchQuery: r})}, {
        icon: Hh,
        label: "Message",
        content: c.jsx(ED, {searchQuery: r})
    }, {icon: n1, label: "Logic", content: c.jsx(kD, {searchQuery: r})}, {
        icon: Zw,
        label: "AI",
        content: c.jsx(_D, {searchQuery: r})
    }, {icon: Yj, label: "Data", content: c.jsx(jD, {searchQuery: r, onOpenDataSidebar: n})}, {
        icon: ra,
        label: "Web",
        content: c.jsx(PD, {searchQuery: r})
    }], p = f.slice(0, 3), h = f.slice(3);
    return c.jsxs("div", {
        className: "flex flex-col items-center",
        children: [c.jsx("div", {
            className: "bg-white/95 backdrop-blur-sm rounded-lg shadow-sm p-4 w-full",
            children: c.jsx("div", {
                className: "flex flex-col items-center space-y-5",
                children: p.map((x, m) => c.jsxs(W0, {
                    open: s === m,
                    onOpenChange: b => a(b ? m : null),
                    children: [c.jsx(K0, {
                        asChild: !0,
                        children: c.jsxs("div", {
                            className: "flex flex-col items-center space-y-1 group",
                            onMouseEnter: () => l(m),
                            onMouseLeave: u,
                            children: [c.jsx(le, {
                                variant: "ghost",
                                size: "icon",
                                className: "h-14 w-14 data-[state=open]:bg-accent",
                                children: c.jsx(x.icon, {className: "h-8 w-8 text-gray-700"})
                            }), c.jsx("span", {className: "text-xs font-medium text-gray-600", children: x.label})]
                        })
                    }), c.jsx(jp, {
                        className: "w-80 p-4 popover-panel",
                        align: "center",
                        side: "right",
                        onMouseEnter: () => l(m),
                        onMouseLeave: u,
                        children: x.content
                    })]
                }, m))
            })
        }), c.jsxs("div", {
            className: `relative bg-[#121213] backdrop-blur-sm rounded-lg shadow-lg p-4 w-full mt-4 
                      group animate-pulse-subtle border border-gray-800 
                      before:absolute before:inset-0 before:rounded-lg before:bg-gradient-to-r 
                      before:from-indigo-500/20 before:via-purple-500/20 before:to-pink-500/20 
                      before:opacity-30 before:blur-xl before:animate-gradient-x overflow-hidden hidden`,
            children: [c.jsx("div", {
                className: "relative z-10 flex flex-col items-center space-y-5",
                children: h.map((x, m) => {
                    const b = m + p.length;
                    return c.jsxs(W0, {
                        open: s === b,
                        onOpenChange: y => a(y ? b : null),
                        children: [c.jsx(K0, {
                            asChild: !0,
                            children: c.jsxs("div", {
                                className: "flex flex-col items-center space-y-1 group",
                                onMouseEnter: () => l(b),
                                onMouseLeave: u,
                                onClick: x.label === "Data" ? d : void 0,
                                children: [c.jsx(le, {
                                    variant: "ghost",
                                    size: "icon",
                                    className: "h-14 w-14 data-[state=open]:bg-gray-800 hover:bg-gray-800/50 transition-all",
                                    children: c.jsx(x.icon, {className: "h-8 w-8 text-gray-300 group-hover:text-white transition-colors"})
                                }), c.jsx("span", {
                                    className: "text-xs font-medium text-gray-400 group-hover:text-gray-200 transition-colors",
                                    children: x.label
                                })]
                            })
                        }), c.jsx(jp, {
                            className: "w-80 p-4 popover-panel bg-gray-900/95 border border-gray-700 text-gray-200",
                            align: "center",
                            side: "right",
                            onMouseEnter: () => l(b),
                            onMouseLeave: u,
                            children: x.content
                        })]
                    }, b)
                })
            }), c.jsx("div", {className: "absolute -bottom-1 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500 to-transparent opacity-70"}), c.jsx("div", {className: "absolute -top-1 left-0 right-0 h-px bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-70"})]
        })]
    })
};
var D$ = g.createContext(void 0);

function Hu(e) {
    const t = g.useContext(D$);
    return e || t || "ltr"
}

var of = "rovingFocusGroup.onEntryFocus", $$ = {bubbles: !1, cancelable: !0},
    Uu = "RovingFocusGroup", [Pp, cS, L$] = fu(Uu), [F$, Wu] = mn(Uu, [L$]), [z$, B$] = F$(Uu),
    uS = g.forwardRef((e, t) => c.jsx(Pp.Provider, {
        scope: e.__scopeRovingFocusGroup,
        children: c.jsx(Pp.Slot, {scope: e.__scopeRovingFocusGroup, children: c.jsx(V$, {...e, ref: t})})
    }));
uS.displayName = Uu;
var V$ = g.forwardRef((e, t) => {
    const {
        __scopeRovingFocusGroup: n,
        orientation: r,
        loop: o = !1,
        dir: s,
        currentTabStopId: i,
        defaultCurrentTabStopId: a,
        onCurrentTabStopIdChange: l,
        onEntryFocus: u,
        preventScrollOnEntryFocus: d = !1,
        ...f
    } = e, p = g.useRef(null), h = ge(t, p), x = Hu(s), [m = null, b] = To({
        prop: i,
        defaultProp: a,
        onChange: l
    }), [y, w] = g.useState(!1), v = Xe(u), C = cS(n), S = g.useRef(!1), [N, E] = g.useState(0);
    return g.useEffect(() => {
        const T = p.current;
        if (T) return T.addEventListener(of, v), () => T.removeEventListener(of, v)
    }, [v]), c.jsx(z$, {
        scope: n,
        orientation: r,
        dir: x,
        loop: o,
        currentTabStopId: m,
        onItemFocus: g.useCallback(T => b(T), [b]),
        onItemShiftTab: g.useCallback(() => w(!0), []),
        onFocusableItemAdd: g.useCallback(() => E(T => T + 1), []),
        onFocusableItemRemove: g.useCallback(() => E(T => T - 1), []),
        children: c.jsx(re.div, {
            tabIndex: y || N === 0 ? -1 : 0,
            "data-orientation": r, ...f,
            ref: h,
            style: {outline: "none", ...e.style},
            onMouseDown: J(e.onMouseDown, () => {
                S.current = !0
            }),
            onFocus: J(e.onFocus, T => {
                const O = !S.current;
                if (T.target === T.currentTarget && O && !y) {
                    const D = new CustomEvent(of, $$);
                    if (T.currentTarget.dispatchEvent(D), !D.defaultPrevented) {
                        const L = C().filter(j => j.focusable), $ = L.find(j => j.active), B = L.find(j => j.id === m),
                            M = [$, B, ...L].filter(Boolean).map(j => j.ref.current);
                        pS(M, d)
                    }
                }
                S.current = !1
            }),
            onBlur: J(e.onBlur, () => w(!1))
        })
    })
}), dS = "RovingFocusGroupItem", fS = g.forwardRef((e, t) => {
    const {__scopeRovingFocusGroup: n, focusable: r = !0, active: o = !1, tabStopId: s, ...i} = e, a = ct(), l = s || a,
        u = B$(dS, n), d = u.currentTabStopId === l, f = cS(n), {onFocusableItemAdd: p, onFocusableItemRemove: h} = u;
    return g.useEffect(() => {
        if (r) return p(), () => h()
    }, [r, p, h]), c.jsx(Pp.ItemSlot, {
        scope: n,
        id: l,
        focusable: r,
        active: o,
        children: c.jsx(re.span, {
            tabIndex: d ? 0 : -1,
            "data-orientation": u.orientation, ...i,
            ref: t,
            onMouseDown: J(e.onMouseDown, x => {
                r ? u.onItemFocus(l) : x.preventDefault()
            }),
            onFocus: J(e.onFocus, () => u.onItemFocus(l)),
            onKeyDown: J(e.onKeyDown, x => {
                if (x.key === "Tab" && x.shiftKey) {
                    u.onItemShiftTab();
                    return
                }
                if (x.target !== x.currentTarget) return;
                const m = W$(x, u.orientation, u.dir);
                if (m !== void 0) {
                    if (x.metaKey || x.ctrlKey || x.altKey || x.shiftKey) return;
                    x.preventDefault();
                    let y = f().filter(w => w.focusable).map(w => w.ref.current);
                    if (m === "last") y.reverse(); else if (m === "prev" || m === "next") {
                        m === "prev" && y.reverse();
                        const w = y.indexOf(x.currentTarget);
                        y = u.loop ? K$(y, w + 1) : y.slice(w + 1)
                    }
                    setTimeout(() => pS(y))
                }
            })
        })
    })
});
fS.displayName = dS;
var H$ = {
    ArrowLeft: "prev",
    ArrowUp: "prev",
    ArrowRight: "next",
    ArrowDown: "next",
    PageUp: "first",
    Home: "first",
    PageDown: "last",
    End: "last"
};

function U$(e, t) {
    return t !== "rtl" ? e : e === "ArrowLeft" ? "ArrowRight" : e === "ArrowRight" ? "ArrowLeft" : e
}

function W$(e, t, n) {
    const r = U$(e.key, n);
    if (!(t === "vertical" && ["ArrowLeft", "ArrowRight"].includes(r)) && !(t === "horizontal" && ["ArrowUp", "ArrowDown"].includes(r))) return H$[r]
}

function pS(e, t = !1) {
    const n = document.activeElement;
    for (const r of e) if (r === n || (r.focus({preventScroll: t}), document.activeElement !== n)) return
}

function K$(e, t) {
    return e.map((n, r) => e[(t + r) % e.length])
}

var hS = uS, mS = fS, Tp = ["Enter", " "], Y$ = ["ArrowDown", "PageUp", "Home"], gS = ["ArrowUp", "PageDown", "End"],
    G$ = [...Y$, ...gS], X$ = {ltr: [...Tp, "ArrowRight"], rtl: [...Tp, "ArrowLeft"]},
    q$ = {ltr: ["ArrowLeft"], rtl: ["ArrowRight"]}, za = "Menu", [ya, Q$, Z$] = fu(za), [Bo, vS] = mn(za, [Z$, eo, Wu]),
    Ku = eo(), yS = Wu(), [J$, Vo] = Bo(za), [eL, Ba] = Bo(za), xS = e => {
        const {__scopeMenu: t, open: n = !1, children: r, dir: o, onOpenChange: s, modal: i = !0} = e,
            a = Ku(t), [l, u] = g.useState(null), d = g.useRef(!1), f = Xe(s), p = Hu(o);
        return g.useEffect(() => {
            const h = () => {
                d.current = !0, document.addEventListener("pointerdown", x, {
                    capture: !0,
                    once: !0
                }), document.addEventListener("pointermove", x, {capture: !0, once: !0})
            }, x = () => d.current = !1;
            return document.addEventListener("keydown", h, {capture: !0}), () => {
                document.removeEventListener("keydown", h, {capture: !0}), document.removeEventListener("pointerdown", x, {capture: !0}), document.removeEventListener("pointermove", x, {capture: !0})
            }
        }, []), c.jsx(em, {
            ...a,
            children: c.jsx(J$, {
                scope: t,
                open: n,
                onOpenChange: f,
                content: l,
                onContentChange: u,
                children: c.jsx(eL, {
                    scope: t,
                    onClose: g.useCallback(() => f(!1), [f]),
                    isUsingKeyboardRef: d,
                    dir: p,
                    modal: i,
                    children: r
                })
            })
        })
    };
xS.displayName = za;
var tL = "MenuAnchor", wm = g.forwardRef((e, t) => {
    const {__scopeMenu: n, ...r} = e, o = Ku(n);
    return c.jsx(Ma, {...o, ...r, ref: t})
});
wm.displayName = tL;
var bm = "MenuPortal", [nL, wS] = Bo(bm, {forceMount: void 0}), bS = e => {
    const {__scopeMenu: t, forceMount: n, children: r, container: o} = e, s = Vo(bm, t);
    return c.jsx(nL, {
        scope: t,
        forceMount: n,
        children: c.jsx(Dt, {present: n || s.open, children: c.jsx(Qs, {asChild: !0, container: o, children: r})})
    })
};
bS.displayName = bm;
var Ht = "MenuContent", [rL, Cm] = Bo(Ht), CS = g.forwardRef((e, t) => {
    const n = wS(Ht, e.__scopeMenu), {forceMount: r = n.forceMount, ...o} = e, s = Vo(Ht, e.__scopeMenu),
        i = Ba(Ht, e.__scopeMenu);
    return c.jsx(ya.Provider, {
        scope: e.__scopeMenu,
        children: c.jsx(Dt, {
            present: r || s.open,
            children: c.jsx(ya.Slot, {
                scope: e.__scopeMenu,
                children: i.modal ? c.jsx(oL, {...o, ref: t}) : c.jsx(sL, {...o, ref: t})
            })
        })
    })
}), oL = g.forwardRef((e, t) => {
    const n = Vo(Ht, e.__scopeMenu), r = g.useRef(null), o = ge(t, r);
    return g.useEffect(() => {
        const s = r.current;
        if (s) return Bu(s)
    }, []), c.jsx(Sm, {
        ...e,
        ref: o,
        trapFocus: n.open,
        disableOutsidePointerEvents: n.open,
        disableOutsideScroll: !0,
        onFocusOutside: J(e.onFocusOutside, s => s.preventDefault(), {checkForDefaultPrevented: !1}),
        onDismiss: () => n.onOpenChange(!1)
    })
}), sL = g.forwardRef((e, t) => {
    const n = Vo(Ht, e.__scopeMenu);
    return c.jsx(Sm, {
        ...e,
        ref: t,
        trapFocus: !1,
        disableOutsidePointerEvents: !1,
        disableOutsideScroll: !1,
        onDismiss: () => n.onOpenChange(!1)
    })
}), iL = Kr("MenuContent.ScrollLock"), Sm = g.forwardRef((e, t) => {
    const {
            __scopeMenu: n,
            loop: r = !1,
            trapFocus: o,
            onOpenAutoFocus: s,
            onCloseAutoFocus: i,
            disableOutsidePointerEvents: a,
            onEntryFocus: l,
            onEscapeKeyDown: u,
            onPointerDownOutside: d,
            onFocusOutside: f,
            onInteractOutside: p,
            onDismiss: h,
            disableOutsideScroll: x,
            ...m
        } = e, b = Vo(Ht, n), y = Ba(Ht, n), w = Ku(n), v = yS(n), C = Q$(n), [S, N] = g.useState(null), E = g.useRef(null),
        T = ge(t, E, b.onContentChange), O = g.useRef(0), D = g.useRef(""), L = g.useRef(0), $ = g.useRef(null),
        B = g.useRef("right"), k = g.useRef(0), M = x ? La : g.Fragment, j = x ? {as: iL, allowPinchZoom: !0} : void 0,
        A = _ => {
            var F, Y;
            const R = D.current + _, z = C().filter(X => !X.disabled), I = document.activeElement,
                V = (F = z.find(X => X.ref.current === I)) == null ? void 0 : F.textValue, H = z.map(X => X.textValue),
                U = yL(H, R, V), K = (Y = z.find(X => X.textValue === U)) == null ? void 0 : Y.ref.current;
            (function X(te) {
                D.current = te, window.clearTimeout(O.current), te !== "" && (O.current = window.setTimeout(() => X(""), 1e3))
            })(R), K && setTimeout(() => K.focus())
        };
    g.useEffect(() => () => window.clearTimeout(O.current), []), zu();
    const P = g.useCallback(_ => {
        var z, I;
        return B.current === ((z = $.current) == null ? void 0 : z.side) && wL(_, (I = $.current) == null ? void 0 : I.area)
    }, []);
    return c.jsx(rL, {
        scope: n, searchRef: D, onItemEnter: g.useCallback(_ => {
            P(_) && _.preventDefault()
        }, [P]), onItemLeave: g.useCallback(_ => {
            var R;
            P(_) || ((R = E.current) == null || R.focus(), N(null))
        }, [P]), onTriggerLeave: g.useCallback(_ => {
            P(_) && _.preventDefault()
        }, [P]), pointerGraceTimerRef: L, onPointerGraceIntentChange: g.useCallback(_ => {
            $.current = _
        }, []), children: c.jsx(M, {
            ...j, children: c.jsx($a, {
                asChild: !0, trapped: o, onMountAutoFocus: J(s, _ => {
                    var R;
                    _.preventDefault(), (R = E.current) == null || R.focus({preventScroll: !0})
                }), onUnmountAutoFocus: i, children: c.jsx(zo, {
                    asChild: !0,
                    disableOutsidePointerEvents: a,
                    onEscapeKeyDown: u,
                    onPointerDownOutside: d,
                    onFocusOutside: f,
                    onInteractOutside: p,
                    onDismiss: h,
                    children: c.jsx(hS, {
                        asChild: !0, ...v,
                        dir: y.dir,
                        orientation: "vertical",
                        loop: r,
                        currentTabStopId: S,
                        onCurrentTabStopIdChange: N,
                        onEntryFocus: J(l, _ => {
                            y.isUsingKeyboardRef.current || _.preventDefault()
                        }),
                        preventScrollOnEntryFocus: !0,
                        children: c.jsx(wu, {
                            role: "menu",
                            "aria-orientation": "vertical",
                            "data-state": LS(b.open),
                            "data-radix-menu-content": "",
                            dir: y.dir, ...w, ...m,
                            ref: T,
                            style: {outline: "none", ...m.style},
                            onKeyDown: J(m.onKeyDown, _ => {
                                const z = _.target.closest("[data-radix-menu-content]") === _.currentTarget,
                                    I = _.ctrlKey || _.altKey || _.metaKey, V = _.key.length === 1;
                                z && (_.key === "Tab" && _.preventDefault(), !I && V && A(_.key));
                                const H = E.current;
                                if (_.target !== H || !G$.includes(_.key)) return;
                                _.preventDefault();
                                const K = C().filter(F => !F.disabled).map(F => F.ref.current);
                                gS.includes(_.key) && K.reverse(), gL(K)
                            }),
                            onBlur: J(e.onBlur, _ => {
                                _.currentTarget.contains(_.target) || (window.clearTimeout(O.current), D.current = "")
                            }),
                            onPointerMove: J(e.onPointerMove, xa(_ => {
                                const R = _.target, z = k.current !== _.clientX;
                                if (_.currentTarget.contains(R) && z) {
                                    const I = _.clientX > k.current ? "right" : "left";
                                    B.current = I, k.current = _.clientX
                                }
                            }))
                        })
                    })
                })
            })
        })
    })
});
CS.displayName = Ht;
var aL = "MenuGroup", Nm = g.forwardRef((e, t) => {
    const {__scopeMenu: n, ...r} = e;
    return c.jsx(re.div, {role: "group", ...r, ref: t})
});
Nm.displayName = aL;
var lL = "MenuLabel", SS = g.forwardRef((e, t) => {
    const {__scopeMenu: n, ...r} = e;
    return c.jsx(re.div, {...r, ref: t})
});
SS.displayName = lL;
var Wc = "MenuItem", Y0 = "menu.itemSelect", Yu = g.forwardRef((e, t) => {
    const {disabled: n = !1, onSelect: r, ...o} = e, s = g.useRef(null), i = Ba(Wc, e.__scopeMenu),
        a = Cm(Wc, e.__scopeMenu), l = ge(t, s), u = g.useRef(!1), d = () => {
            const f = s.current;
            if (!n && f) {
                const p = new CustomEvent(Y0, {bubbles: !0, cancelable: !0});
                f.addEventListener(Y0, h => r == null ? void 0 : r(h), {once: !0}), Dh(f, p), p.defaultPrevented ? u.current = !1 : i.onClose()
            }
        };
    return c.jsx(NS, {
        ...o, ref: l, disabled: n, onClick: J(e.onClick, d), onPointerDown: f => {
            var p;
            (p = e.onPointerDown) == null || p.call(e, f), u.current = !0
        }, onPointerUp: J(e.onPointerUp, f => {
            var p;
            u.current || (p = f.currentTarget) == null || p.click()
        }), onKeyDown: J(e.onKeyDown, f => {
            const p = a.searchRef.current !== "";
            n || p && f.key === " " || Tp.includes(f.key) && (f.currentTarget.click(), f.preventDefault())
        })
    })
});
Yu.displayName = Wc;
var NS = g.forwardRef((e, t) => {
    const {__scopeMenu: n, disabled: r = !1, textValue: o, ...s} = e, i = Cm(Wc, n), a = yS(n), l = g.useRef(null),
        u = ge(t, l), [d, f] = g.useState(!1), [p, h] = g.useState("");
    return g.useEffect(() => {
        const x = l.current;
        x && h((x.textContent ?? "").trim())
    }, [s.children]), c.jsx(ya.ItemSlot, {
        scope: n,
        disabled: r,
        textValue: o ?? p,
        children: c.jsx(mS, {
            asChild: !0, ...a,
            focusable: !r,
            children: c.jsx(re.div, {
                role: "menuitem",
                "data-highlighted": d ? "" : void 0,
                "aria-disabled": r || void 0,
                "data-disabled": r ? "" : void 0, ...s,
                ref: u,
                onPointerMove: J(e.onPointerMove, xa(x => {
                    r ? i.onItemLeave(x) : (i.onItemEnter(x), x.defaultPrevented || x.currentTarget.focus({preventScroll: !0}))
                })),
                onPointerLeave: J(e.onPointerLeave, xa(x => i.onItemLeave(x))),
                onFocus: J(e.onFocus, () => f(!0)),
                onBlur: J(e.onBlur, () => f(!1))
            })
        })
    })
}), cL = "MenuCheckboxItem", ES = g.forwardRef((e, t) => {
    const {checked: n = !1, onCheckedChange: r, ...o} = e;
    return c.jsx(TS, {
        scope: e.__scopeMenu,
        checked: n,
        children: c.jsx(Yu, {
            role: "menuitemcheckbox",
            "aria-checked": Kc(n) ? "mixed" : n, ...o,
            ref: t,
            "data-state": km(n),
            onSelect: J(o.onSelect, () => r == null ? void 0 : r(Kc(n) ? !0 : !n), {checkForDefaultPrevented: !1})
        })
    })
});
ES.displayName = cL;
var kS = "MenuRadioGroup", [uL, dL] = Bo(kS, {
    value: void 0, onValueChange: () => {
    }
}), _S = g.forwardRef((e, t) => {
    const {value: n, onValueChange: r, ...o} = e, s = Xe(r);
    return c.jsx(uL, {scope: e.__scopeMenu, value: n, onValueChange: s, children: c.jsx(Nm, {...o, ref: t})})
});
_S.displayName = kS;
var jS = "MenuRadioItem", PS = g.forwardRef((e, t) => {
    const {value: n, ...r} = e, o = dL(jS, e.__scopeMenu), s = n === o.value;
    return c.jsx(TS, {
        scope: e.__scopeMenu,
        checked: s,
        children: c.jsx(Yu, {
            role: "menuitemradio",
            "aria-checked": s, ...r,
            ref: t,
            "data-state": km(s),
            onSelect: J(r.onSelect, () => {
                var i;
                return (i = o.onValueChange) == null ? void 0 : i.call(o, n)
            }, {checkForDefaultPrevented: !1})
        })
    })
});
PS.displayName = jS;
var Em = "MenuItemIndicator", [TS, fL] = Bo(Em, {checked: !1}), MS = g.forwardRef((e, t) => {
    const {__scopeMenu: n, forceMount: r, ...o} = e, s = fL(Em, n);
    return c.jsx(Dt, {
        present: r || Kc(s.checked) || s.checked === !0,
        children: c.jsx(re.span, {...o, ref: t, "data-state": km(s.checked)})
    })
});
MS.displayName = Em;
var pL = "MenuSeparator", RS = g.forwardRef((e, t) => {
    const {__scopeMenu: n, ...r} = e;
    return c.jsx(re.div, {role: "separator", "aria-orientation": "horizontal", ...r, ref: t})
});
RS.displayName = pL;
var hL = "MenuArrow", AS = g.forwardRef((e, t) => {
    const {__scopeMenu: n, ...r} = e, o = Ku(n);
    return c.jsx(bu, {...o, ...r, ref: t})
});
AS.displayName = hL;
var mL = "MenuSub", [Cz, IS] = Bo(mL), ki = "MenuSubTrigger", OS = g.forwardRef((e, t) => {
    const n = Vo(ki, e.__scopeMenu), r = Ba(ki, e.__scopeMenu), o = IS(ki, e.__scopeMenu), s = Cm(ki, e.__scopeMenu),
        i = g.useRef(null), {pointerGraceTimerRef: a, onPointerGraceIntentChange: l} = s,
        u = {__scopeMenu: e.__scopeMenu}, d = g.useCallback(() => {
            i.current && window.clearTimeout(i.current), i.current = null
        }, []);
    return g.useEffect(() => d, [d]), g.useEffect(() => {
        const f = a.current;
        return () => {
            window.clearTimeout(f), l(null)
        }
    }, [a, l]), c.jsx(wm, {
        asChild: !0, ...u, children: c.jsx(NS, {
            id: o.triggerId,
            "aria-haspopup": "menu",
            "aria-expanded": n.open,
            "aria-controls": o.contentId,
            "data-state": LS(n.open), ...e,
            ref: Wr(t, o.onTriggerChange),
            onClick: f => {
                var p;
                (p = e.onClick) == null || p.call(e, f), !(e.disabled || f.defaultPrevented) && (f.currentTarget.focus(), n.open || n.onOpenChange(!0))
            },
            onPointerMove: J(e.onPointerMove, xa(f => {
                s.onItemEnter(f), !f.defaultPrevented && !e.disabled && !n.open && !i.current && (s.onPointerGraceIntentChange(null), i.current = window.setTimeout(() => {
                    n.onOpenChange(!0), d()
                }, 100))
            })),
            onPointerLeave: J(e.onPointerLeave, xa(f => {
                var h, x;
                d();
                const p = (h = n.content) == null ? void 0 : h.getBoundingClientRect();
                if (p) {
                    const m = (x = n.content) == null ? void 0 : x.dataset.side, b = m === "right", y = b ? -5 : 5,
                        w = p[b ? "left" : "right"], v = p[b ? "right" : "left"];
                    s.onPointerGraceIntentChange({
                        area: [{x: f.clientX + y, y: f.clientY}, {x: w, y: p.top}, {
                            x: v,
                            y: p.top
                        }, {x: v, y: p.bottom}, {x: w, y: p.bottom}], side: m
                    }), window.clearTimeout(a.current), a.current = window.setTimeout(() => s.onPointerGraceIntentChange(null), 300)
                } else {
                    if (s.onTriggerLeave(f), f.defaultPrevented) return;
                    s.onPointerGraceIntentChange(null)
                }
            })),
            onKeyDown: J(e.onKeyDown, f => {
                var h;
                const p = s.searchRef.current !== "";
                e.disabled || p && f.key === " " || X$[r.dir].includes(f.key) && (n.onOpenChange(!0), (h = n.content) == null || h.focus(), f.preventDefault())
            })
        })
    })
});
OS.displayName = ki;
var DS = "MenuSubContent", $S = g.forwardRef((e, t) => {
    const n = wS(Ht, e.__scopeMenu), {forceMount: r = n.forceMount, ...o} = e, s = Vo(Ht, e.__scopeMenu),
        i = Ba(Ht, e.__scopeMenu), a = IS(DS, e.__scopeMenu), l = g.useRef(null), u = ge(t, l);
    return c.jsx(ya.Provider, {
        scope: e.__scopeMenu,
        children: c.jsx(Dt, {
            present: r || s.open,
            children: c.jsx(ya.Slot, {
                scope: e.__scopeMenu,
                children: c.jsx(Sm, {
                    id: a.contentId,
                    "aria-labelledby": a.triggerId, ...o,
                    ref: u,
                    align: "start",
                    side: i.dir === "rtl" ? "left" : "right",
                    disableOutsidePointerEvents: !1,
                    disableOutsideScroll: !1,
                    trapFocus: !1,
                    onOpenAutoFocus: d => {
                        var f;
                        i.isUsingKeyboardRef.current && ((f = l.current) == null || f.focus()), d.preventDefault()
                    },
                    onCloseAutoFocus: d => d.preventDefault(),
                    onFocusOutside: J(e.onFocusOutside, d => {
                        d.target !== a.trigger && s.onOpenChange(!1)
                    }),
                    onEscapeKeyDown: J(e.onEscapeKeyDown, d => {
                        i.onClose(), d.preventDefault()
                    }),
                    onKeyDown: J(e.onKeyDown, d => {
                        var h;
                        const f = d.currentTarget.contains(d.target), p = q$[i.dir].includes(d.key);
                        f && p && (s.onOpenChange(!1), (h = a.trigger) == null || h.focus(), d.preventDefault())
                    })
                })
            })
        })
    })
});
$S.displayName = DS;

function LS(e) {
    return e ? "open" : "closed"
}

function Kc(e) {
    return e === "indeterminate"
}

function km(e) {
    return Kc(e) ? "indeterminate" : e ? "checked" : "unchecked"
}

function gL(e) {
    const t = document.activeElement;
    for (const n of e) if (n === t || (n.focus(), document.activeElement !== t)) return
}

function vL(e, t) {
    return e.map((n, r) => e[(t + r) % e.length])
}

function yL(e, t, n) {
    const o = t.length > 1 && Array.from(t).every(u => u === t[0]) ? t[0] : t, s = n ? e.indexOf(n) : -1;
    let i = vL(e, Math.max(s, 0));
    o.length === 1 && (i = i.filter(u => u !== n));
    const l = i.find(u => u.toLowerCase().startsWith(o.toLowerCase()));
    return l !== n ? l : void 0
}

function xL(e, t) {
    const {x: n, y: r} = e;
    let o = !1;
    for (let s = 0, i = t.length - 1; s < t.length; i = s++) {
        const a = t[s].x, l = t[s].y, u = t[i].x, d = t[i].y;
        l > r != d > r && n < (u - a) * (r - l) / (d - l) + a && (o = !o)
    }
    return o
}

function wL(e, t) {
    if (!t) return !1;
    const n = {x: e.clientX, y: e.clientY};
    return xL(n, t)
}

function xa(e) {
    return t => t.pointerType === "mouse" ? e(t) : void 0
}

var bL = xS, CL = wm, SL = bS, NL = CS, EL = Nm, kL = SS, _L = Yu, jL = ES, PL = _S, TL = PS, ML = MS, RL = RS, AL = AS,
    IL = OS, OL = $S, _m = "ContextMenu", [DL, Sz] = mn(_m, [vS]), ft = vS(), [$L, FS] = DL(_m), zS = e => {
        const {__scopeContextMenu: t, children: n, onOpenChange: r, dir: o, modal: s = !0} = e, [i, a] = g.useState(!1),
            l = ft(t), u = Xe(r), d = g.useCallback(f => {
                a(f), u(f)
            }, [u]);
        return c.jsx($L, {
            scope: t,
            open: i,
            onOpenChange: d,
            modal: s,
            children: c.jsx(bL, {...l, dir: o, open: i, onOpenChange: d, modal: s, children: n})
        })
    };
zS.displayName = _m;
var BS = "ContextMenuTrigger", VS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, disabled: r = !1, ...o} = e, s = FS(BS, n), i = ft(n), a = g.useRef({x: 0, y: 0}),
        l = g.useRef({getBoundingClientRect: () => DOMRect.fromRect({width: 0, height: 0, ...a.current})}),
        u = g.useRef(0), d = g.useCallback(() => window.clearTimeout(u.current), []), f = p => {
            a.current = {x: p.clientX, y: p.clientY}, s.onOpenChange(!0)
        };
    return g.useEffect(() => d, [d]), g.useEffect(() => void (r && d()), [r, d]), c.jsxs(c.Fragment, {
        children: [c.jsx(CL, {
            ...i,
            virtualRef: l
        }), c.jsx(re.span, {
            "data-state": s.open ? "open" : "closed",
            "data-disabled": r ? "" : void 0, ...o,
            ref: t,
            style: {WebkitTouchCallout: "none", ...e.style},
            onContextMenu: r ? e.onContextMenu : J(e.onContextMenu, p => {
                d(), f(p), p.preventDefault()
            }),
            onPointerDown: r ? e.onPointerDown : J(e.onPointerDown, Dl(p => {
                d(), u.current = window.setTimeout(() => f(p), 700)
            })),
            onPointerMove: r ? e.onPointerMove : J(e.onPointerMove, Dl(d)),
            onPointerCancel: r ? e.onPointerCancel : J(e.onPointerCancel, Dl(d)),
            onPointerUp: r ? e.onPointerUp : J(e.onPointerUp, Dl(d))
        })]
    })
});
VS.displayName = BS;
var LL = "ContextMenuPortal", HS = e => {
    const {__scopeContextMenu: t, ...n} = e, r = ft(t);
    return c.jsx(SL, {...r, ...n})
};
HS.displayName = LL;
var US = "ContextMenuContent", WS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = FS(US, n), s = ft(n), i = g.useRef(!1);
    return c.jsx(NL, {
        ...s, ...r,
        ref: t,
        side: "right",
        sideOffset: 2,
        align: "start",
        onCloseAutoFocus: a => {
            var l;
            (l = e.onCloseAutoFocus) == null || l.call(e, a), !a.defaultPrevented && i.current && a.preventDefault(), i.current = !1
        },
        onInteractOutside: a => {
            var l;
            (l = e.onInteractOutside) == null || l.call(e, a), !a.defaultPrevented && !o.modal && (i.current = !0)
        },
        style: {
            ...e.style,
            "--radix-context-menu-content-transform-origin": "var(--radix-popper-transform-origin)",
            "--radix-context-menu-content-available-width": "var(--radix-popper-available-width)",
            "--radix-context-menu-content-available-height": "var(--radix-popper-available-height)",
            "--radix-context-menu-trigger-width": "var(--radix-popper-anchor-width)",
            "--radix-context-menu-trigger-height": "var(--radix-popper-anchor-height)"
        }
    })
});
WS.displayName = US;
var FL = "ContextMenuGroup", zL = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(EL, {...o, ...r, ref: t})
});
zL.displayName = FL;
var BL = "ContextMenuLabel", KS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(kL, {...o, ...r, ref: t})
});
KS.displayName = BL;
var VL = "ContextMenuItem", YS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(_L, {...o, ...r, ref: t})
});
YS.displayName = VL;
var HL = "ContextMenuCheckboxItem", GS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(jL, {...o, ...r, ref: t})
});
GS.displayName = HL;
var UL = "ContextMenuRadioGroup", WL = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(PL, {...o, ...r, ref: t})
});
WL.displayName = UL;
var KL = "ContextMenuRadioItem", XS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(TL, {...o, ...r, ref: t})
});
XS.displayName = KL;
var YL = "ContextMenuItemIndicator", qS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(ML, {...o, ...r, ref: t})
});
qS.displayName = YL;
var GL = "ContextMenuSeparator", QS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(RL, {...o, ...r, ref: t})
});
QS.displayName = GL;
var XL = "ContextMenuArrow", qL = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(AL, {...o, ...r, ref: t})
});
qL.displayName = XL;
var QL = "ContextMenuSubTrigger", ZS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(IL, {...o, ...r, ref: t})
});
ZS.displayName = QL;
var ZL = "ContextMenuSubContent", JS = g.forwardRef((e, t) => {
    const {__scopeContextMenu: n, ...r} = e, o = ft(n);
    return c.jsx(OL, {
        ...o, ...r,
        ref: t,
        style: {
            ...e.style,
            "--radix-context-menu-content-transform-origin": "var(--radix-popper-transform-origin)",
            "--radix-context-menu-content-available-width": "var(--radix-popper-available-width)",
            "--radix-context-menu-content-available-height": "var(--radix-popper-available-height)",
            "--radix-context-menu-trigger-width": "var(--radix-popper-anchor-width)",
            "--radix-context-menu-trigger-height": "var(--radix-popper-anchor-height)"
        }
    })
});
JS.displayName = ZL;

function Dl(e) {
    return t => t.pointerType !== "mouse" ? e(t) : void 0
}

var JL = zS, eF = VS, tF = HS, eN = WS, tN = KS, nN = YS, rN = GS, oN = XS, sN = qS, iN = QS, aN = ZS, lN = JS;
const no = JL, ro = eF, nF = g.forwardRef(({className: e, inset: t, children: n, ...r}, o) => c.jsxs(aN, {
    ref: o,
    className: se("flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground", t && "pl-8", e), ...r,
    children: [n, c.jsx(Hj, {className: "ml-auto h-4 w-4"})]
}));
nF.displayName = aN.displayName;
const rF = g.forwardRef(({className: e, ...t}, n) => c.jsx(lN, {
    ref: n,
    className: se("z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2", e), ...t
}));
rF.displayName = lN.displayName;
const ir = g.forwardRef(({className: e, ...t}, n) => c.jsx(tF, {
    children: c.jsx(eN, {
        ref: n,
        className: se("z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md animate-in fade-in-80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2", e), ...t
    })
}));
ir.displayName = eN.displayName;
const ar = g.forwardRef(({className: e, inset: t, ...n}, r) => c.jsx(nN, {
    ref: r,
    className: se("relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50", t && "pl-8", e), ...n
}));
ar.displayName = nN.displayName;
const oF = g.forwardRef(({className: e, children: t, checked: n, ...r}, o) => c.jsxs(rN, {
    ref: o,
    className: se("relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50", e),
    checked: n, ...r,
    children: [c.jsx("span", {
        className: "absolute left-2 flex h-3.5 w-3.5 items-center justify-center",
        children: c.jsx(sN, {children: c.jsx(zh, {className: "h-4 w-4"})})
    }), t]
}));
oF.displayName = rN.displayName;
const sF = g.forwardRef(({className: e, children: t, ...n}, r) => c.jsxs(oN, {
    ref: r,
    className: se("relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50", e), ...n,
    children: [c.jsx("span", {
        className: "absolute left-2 flex h-3.5 w-3.5 items-center justify-center",
        children: c.jsx(sN, {children: c.jsx(Wj, {className: "h-2 w-2 fill-current"})})
    }), t]
}));
sF.displayName = oN.displayName;
const iF = g.forwardRef(({className: e, inset: t, ...n}, r) => c.jsx(tN, {
    ref: r,
    className: se("px-2 py-1.5 text-sm font-semibold text-foreground", t && "pl-8", e), ...n
}));
iF.displayName = tN.displayName;
const aF = g.forwardRef(({className: e, ...t}, n) => c.jsx(iN, {
    ref: n,
    className: se("-mx-1 my-1 h-px bg-border", e), ...t
}));
aF.displayName = iN.displayName;
const lr = ({
                id: e,
                title: t,
                icon: n,
                headerBackground: r = "bg-gray-50",
                borderColor: o = "border-gray-100",
                children: s,
                nodeWidth: i = "w-[300px]",
                titleColor: a = "text-gray-800",
                className: l = "",
                handles: u = {left: !0, right: !0}
            }) => {
    const {deleteNode: d} = rt();
    return c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: `${i} rounded-lg border bg-white shadow-md overflow-hidden ${o} ${l}`,
                children: [c.jsxs("div", {
                    className: `flex items-center gap-2 px-3 py-2 border-b ${o} ${r}`,
                    children: [n && c.jsx("div", {
                        className: "flex-shrink-0",
                        children: n
                    }), c.jsx("div", {className: `font-medium text-sm ${a}`, children: t})]
                }), c.jsx("div", {className: "p-4", children: s}), u.left && c.jsx(de, {
                    type: "target",
                    position: q.Left,
                    className: "!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white",
                    style: {left: -6}
                }), u.right && c.jsx(de, {
                    type: "source",
                    position: q.Right,
                    className: "!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white",
                    style: {right: -6}
                }), u.top && c.jsx(de, {
                    type: "target",
                    position: q.Top,
                    className: "!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white",
                    style: {top: -6}
                }), u.bottom && c.jsx(de, {
                    type: "source",
                    position: q.Bottom,
                    className: "!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white",
                    style: {bottom: -6}
                })]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => d(e),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, lF = ({id: e, data: t}) => c.jsx(lr, {
    id: e,
    title: t.label,
    icon: c.jsx(r1, {className: "h-4 w-4 text-yellow-600"}),
    headerBackground: "bg-gray-50",
    borderColor: "border-gray-100",
    handles: {right: !0},
    children: c.jsx("div", {
        className: "text-sm text-gray-600",
        children: c.jsx("p", {children: "This trigger will start your flow when activated."})
    })
}), De = g.forwardRef(({className: e, type: t, ...n}, r) => c.jsx("input", {
    type: t,
    className: se("flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm", e),
    ref: r, ...n
}));
De.displayName = "Input";

function G0(e, [t, n]) {
    return Math.min(n, Math.max(t, e))
}

function cF(e) {
    const t = g.useRef({value: e, previous: e});
    return g.useMemo(() => (t.current.value !== e && (t.current.previous = t.current.value, t.current.value = e), t.current.previous), [e])
}

var uF = [" ", "Enter", "ArrowUp", "ArrowDown"], dF = [" ", "Enter"],
    Va = "Select", [Gu, Xu, fF] = fu(Va), [ni, Nz] = mn(Va, [fF, eo]), qu = eo(), [pF, oo] = ni(Va), [hF, mF] = ni(Va),
    cN = e => {
        const {
                __scopeSelect: t,
                children: n,
                open: r,
                defaultOpen: o,
                onOpenChange: s,
                value: i,
                defaultValue: a,
                onValueChange: l,
                dir: u,
                name: d,
                autoComplete: f,
                disabled: p,
                required: h,
                form: x
            } = e, m = qu(t), [b, y] = g.useState(null), [w, v] = g.useState(null), [C, S] = g.useState(!1),
            N = Hu(u), [E = !1, T] = To({prop: r, defaultProp: o, onChange: s}), [O, D] = To({
                prop: i,
                defaultProp: a,
                onChange: l
            }), L = g.useRef(null), $ = b ? x || !!b.closest("form") : !0, [B, k] = g.useState(new Set),
            M = Array.from(B).map(j => j.props.value).join(";");
        return c.jsx(em, {
            ...m,
            children: c.jsxs(pF, {
                required: h,
                scope: t,
                trigger: b,
                onTriggerChange: y,
                valueNode: w,
                onValueNodeChange: v,
                valueNodeHasChildren: C,
                onValueNodeHasChildrenChange: S,
                contentId: ct(),
                value: O,
                onValueChange: D,
                open: E,
                onOpenChange: T,
                dir: N,
                triggerPointerDownPosRef: L,
                disabled: p,
                children: [c.jsx(Gu.Provider, {
                    scope: t,
                    children: c.jsx(hF, {
                        scope: e.__scopeSelect, onNativeOptionAdd: g.useCallback(j => {
                            k(A => new Set(A).add(j))
                        }, []), onNativeOptionRemove: g.useCallback(j => {
                            k(A => {
                                const P = new Set(A);
                                return P.delete(j), P
                            })
                        }, []), children: n
                    })
                }), $ ? c.jsxs(IN, {
                    "aria-hidden": !0,
                    required: h,
                    tabIndex: -1,
                    name: d,
                    autoComplete: f,
                    value: O,
                    onChange: j => D(j.target.value),
                    disabled: p,
                    form: x,
                    children: [O === void 0 ? c.jsx("option", {value: ""}) : null, Array.from(B)]
                }, M) : null]
            })
        })
    };
cN.displayName = Va;
var uN = "SelectTrigger", dN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, disabled: r = !1, ...o} = e, s = qu(n), i = oo(uN, n), a = i.disabled || r,
        l = ge(t, i.onTriggerChange), u = Xu(n), d = g.useRef("touch"), [f, p, h] = ON(m => {
            const b = u().filter(v => !v.disabled), y = b.find(v => v.value === i.value), w = DN(b, m, y);
            w !== void 0 && i.onValueChange(w.value)
        }), x = m => {
            a || (i.onOpenChange(!0), h()), m && (i.triggerPointerDownPosRef.current = {
                x: Math.round(m.pageX),
                y: Math.round(m.pageY)
            })
        };
    return c.jsx(Ma, {
        asChild: !0, ...s,
        children: c.jsx(re.button, {
            type: "button",
            role: "combobox",
            "aria-controls": i.contentId,
            "aria-expanded": i.open,
            "aria-required": i.required,
            "aria-autocomplete": "none",
            dir: i.dir,
            "data-state": i.open ? "open" : "closed",
            disabled: a,
            "data-disabled": a ? "" : void 0,
            "data-placeholder": AN(i.value) ? "" : void 0, ...o,
            ref: l,
            onClick: J(o.onClick, m => {
                m.currentTarget.focus(), d.current !== "mouse" && x(m)
            }),
            onPointerDown: J(o.onPointerDown, m => {
                d.current = m.pointerType;
                const b = m.target;
                b.hasPointerCapture(m.pointerId) && b.releasePointerCapture(m.pointerId), m.button === 0 && m.ctrlKey === !1 && m.pointerType === "mouse" && (x(m), m.preventDefault())
            }),
            onKeyDown: J(o.onKeyDown, m => {
                const b = f.current !== "";
                !(m.ctrlKey || m.altKey || m.metaKey) && m.key.length === 1 && p(m.key), !(b && m.key === " ") && uF.includes(m.key) && (x(), m.preventDefault())
            })
        })
    })
});
dN.displayName = uN;
var fN = "SelectValue", pN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, className: r, style: o, children: s, placeholder: i = "", ...a} = e,
        l = oo(fN, n), {onValueNodeHasChildrenChange: u} = l, d = s !== void 0, f = ge(t, l.onValueNodeChange);
    return nt(() => {
        u(d)
    }, [u, d]), c.jsx(re.span, {
        ...a,
        ref: f,
        style: {pointerEvents: "none"},
        children: AN(l.value) ? c.jsx(c.Fragment, {children: i}) : s
    })
});
pN.displayName = fN;
var gF = "SelectIcon", hN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, children: r, ...o} = e;
    return c.jsx(re.span, {"aria-hidden": !0, ...o, ref: t, children: r || "▼"})
});
hN.displayName = gF;
var vF = "SelectPortal", mN = e => c.jsx(Qs, {asChild: !0, ...e});
mN.displayName = vF;
var Io = "SelectContent", gN = g.forwardRef((e, t) => {
    const n = oo(Io, e.__scopeSelect), [r, o] = g.useState();
    if (nt(() => {
        o(new DocumentFragment)
    }, []), !n.open) {
        const s = r;
        return s ? Fo.createPortal(c.jsx(vN, {
            scope: e.__scopeSelect,
            children: c.jsx(Gu.Slot, {scope: e.__scopeSelect, children: c.jsx("div", {children: e.children})})
        }), s) : null
    }
    return c.jsx(yN, {...e, ref: t})
});
gN.displayName = Io;
var Qt = 10, [vN, so] = ni(Io), yF = "SelectContentImpl", xF = Kr("SelectContent.RemoveScroll"),
    yN = g.forwardRef((e, t) => {
        const {
                __scopeSelect: n,
                position: r = "item-aligned",
                onCloseAutoFocus: o,
                onEscapeKeyDown: s,
                onPointerDownOutside: i,
                side: a,
                sideOffset: l,
                align: u,
                alignOffset: d,
                arrowPadding: f,
                collisionBoundary: p,
                collisionPadding: h,
                sticky: x,
                hideWhenDetached: m,
                avoidCollisions: b,
                ...y
            } = e, w = oo(Io, n), [v, C] = g.useState(null), [S, N] = g.useState(null),
            E = ge(t, F => C(F)), [T, O] = g.useState(null), [D, L] = g.useState(null),
            $ = Xu(n), [B, k] = g.useState(!1), M = g.useRef(!1);
        g.useEffect(() => {
            if (v) return Bu(v)
        }, [v]), zu();
        const j = g.useCallback(F => {
            const [Y, ...X] = $().map(Q => Q.ref.current), [te] = X.slice(-1), ee = document.activeElement;
            for (const Q of F) if (Q === ee || (Q == null || Q.scrollIntoView({block: "nearest"}), Q === Y && S && (S.scrollTop = 0), Q === te && S && (S.scrollTop = S.scrollHeight), Q == null || Q.focus(), document.activeElement !== ee)) return
        }, [$, S]), A = g.useCallback(() => j([T, v]), [j, T, v]);
        g.useEffect(() => {
            B && A()
        }, [B, A]);
        const {onOpenChange: P, triggerPointerDownPosRef: _} = w;
        g.useEffect(() => {
            if (v) {
                let F = {x: 0, y: 0};
                const Y = te => {
                    var ee, Q;
                    F = {
                        x: Math.abs(Math.round(te.pageX) - (((ee = _.current) == null ? void 0 : ee.x) ?? 0)),
                        y: Math.abs(Math.round(te.pageY) - (((Q = _.current) == null ? void 0 : Q.y) ?? 0))
                    }
                }, X = te => {
                    F.x <= 10 && F.y <= 10 ? te.preventDefault() : v.contains(te.target) || P(!1), document.removeEventListener("pointermove", Y), _.current = null
                };
                return _.current !== null && (document.addEventListener("pointermove", Y), document.addEventListener("pointerup", X, {
                    capture: !0,
                    once: !0
                })), () => {
                    document.removeEventListener("pointermove", Y), document.removeEventListener("pointerup", X, {capture: !0})
                }
            }
        }, [v, P, _]), g.useEffect(() => {
            const F = () => P(!1);
            return window.addEventListener("blur", F), window.addEventListener("resize", F), () => {
                window.removeEventListener("blur", F), window.removeEventListener("resize", F)
            }
        }, [P]);
        const [R, z] = ON(F => {
            const Y = $().filter(ee => !ee.disabled), X = Y.find(ee => ee.ref.current === document.activeElement),
                te = DN(Y, F, X);
            te && setTimeout(() => te.ref.current.focus())
        }), I = g.useCallback((F, Y, X) => {
            const te = !M.current && !X;
            (w.value !== void 0 && w.value === Y || te) && (O(F), te && (M.current = !0))
        }, [w.value]), V = g.useCallback(() => v == null ? void 0 : v.focus(), [v]), H = g.useCallback((F, Y, X) => {
            const te = !M.current && !X;
            (w.value !== void 0 && w.value === Y || te) && L(F)
        }, [w.value]), U = r === "popper" ? Mp : xN, K = U === Mp ? {
            side: a,
            sideOffset: l,
            align: u,
            alignOffset: d,
            arrowPadding: f,
            collisionBoundary: p,
            collisionPadding: h,
            sticky: x,
            hideWhenDetached: m,
            avoidCollisions: b
        } : {};
        return c.jsx(vN, {
            scope: n,
            content: v,
            viewport: S,
            onViewportChange: N,
            itemRefCallback: I,
            selectedItem: T,
            onItemLeave: V,
            itemTextRefCallback: H,
            focusSelectedItem: A,
            selectedItemText: D,
            position: r,
            isPositioned: B,
            searchRef: R,
            children: c.jsx(La, {
                as: xF, allowPinchZoom: !0, children: c.jsx($a, {
                    asChild: !0,
                    trapped: w.open,
                    onMountAutoFocus: F => {
                        F.preventDefault()
                    },
                    onUnmountAutoFocus: J(o, F => {
                        var Y;
                        (Y = w.trigger) == null || Y.focus({preventScroll: !0}), F.preventDefault()
                    }),
                    children: c.jsx(zo, {
                        asChild: !0,
                        disableOutsidePointerEvents: !0,
                        onEscapeKeyDown: s,
                        onPointerDownOutside: i,
                        onFocusOutside: F => F.preventDefault(),
                        onDismiss: () => w.onOpenChange(!1),
                        children: c.jsx(U, {
                            role: "listbox",
                            id: w.contentId,
                            "data-state": w.open ? "open" : "closed",
                            dir: w.dir,
                            onContextMenu: F => F.preventDefault(), ...y, ...K,
                            onPlaced: () => k(!0),
                            ref: E,
                            style: {display: "flex", flexDirection: "column", outline: "none", ...y.style},
                            onKeyDown: J(y.onKeyDown, F => {
                                const Y = F.ctrlKey || F.altKey || F.metaKey;
                                if (F.key === "Tab" && F.preventDefault(), !Y && F.key.length === 1 && z(F.key), ["ArrowUp", "ArrowDown", "Home", "End"].includes(F.key)) {
                                    let te = $().filter(ee => !ee.disabled).map(ee => ee.ref.current);
                                    if (["ArrowUp", "End"].includes(F.key) && (te = te.slice().reverse()), ["ArrowUp", "ArrowDown"].includes(F.key)) {
                                        const ee = F.target, Q = te.indexOf(ee);
                                        te = te.slice(Q + 1)
                                    }
                                    setTimeout(() => j(te)), F.preventDefault()
                                }
                            })
                        })
                    })
                })
            })
        })
    });
yN.displayName = yF;
var wF = "SelectItemAlignedPosition", xN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, onPlaced: r, ...o} = e, s = oo(Io, n),
        i = so(Io, n), [a, l] = g.useState(null), [u, d] = g.useState(null), f = ge(t, E => d(E)), p = Xu(n),
        h = g.useRef(!1), x = g.useRef(!0), {
            viewport: m,
            selectedItem: b,
            selectedItemText: y,
            focusSelectedItem: w
        } = i, v = g.useCallback(() => {
            if (s.trigger && s.valueNode && a && u && m && b && y) {
                const E = s.trigger.getBoundingClientRect(), T = u.getBoundingClientRect(),
                    O = s.valueNode.getBoundingClientRect(), D = y.getBoundingClientRect();
                if (s.dir !== "rtl") {
                    const ee = D.left - T.left, Q = O.left - ee, ue = E.left - Q, ie = E.width + ue,
                        we = Math.max(ie, T.width), Ne = window.innerWidth - Qt, pt = G0(Q, [Qt, Math.max(Qt, Ne - we)]);
                    a.style.minWidth = ie + "px", a.style.left = pt + "px"
                } else {
                    const ee = T.right - D.right, Q = window.innerWidth - O.right - ee,
                        ue = window.innerWidth - E.right - Q, ie = E.width + ue, we = Math.max(ie, T.width),
                        Ne = window.innerWidth - Qt, pt = G0(Q, [Qt, Math.max(Qt, Ne - we)]);
                    a.style.minWidth = ie + "px", a.style.right = pt + "px"
                }
                const L = p(), $ = window.innerHeight - Qt * 2, B = m.scrollHeight, k = window.getComputedStyle(u),
                    M = parseInt(k.borderTopWidth, 10), j = parseInt(k.paddingTop, 10),
                    A = parseInt(k.borderBottomWidth, 10), P = parseInt(k.paddingBottom, 10), _ = M + j + B + P + A,
                    R = Math.min(b.offsetHeight * 5, _), z = window.getComputedStyle(m), I = parseInt(z.paddingTop, 10),
                    V = parseInt(z.paddingBottom, 10), H = E.top + E.height / 2 - Qt, U = $ - H, K = b.offsetHeight / 2,
                    F = b.offsetTop + K, Y = M + j + F, X = _ - Y;
                if (Y <= H) {
                    const ee = L.length > 0 && b === L[L.length - 1].ref.current;
                    a.style.bottom = "0px";
                    const Q = u.clientHeight - m.offsetTop - m.offsetHeight, ue = Math.max(U, K + (ee ? V : 0) + Q + A),
                        ie = Y + ue;
                    a.style.height = ie + "px"
                } else {
                    const ee = L.length > 0 && b === L[0].ref.current;
                    a.style.top = "0px";
                    const ue = Math.max(H, M + m.offsetTop + (ee ? I : 0) + K) + X;
                    a.style.height = ue + "px", m.scrollTop = Y - H + m.offsetTop
                }
                a.style.margin = `${Qt}px 0`, a.style.minHeight = R + "px", a.style.maxHeight = $ + "px", r == null || r(), requestAnimationFrame(() => h.current = !0)
            }
        }, [p, s.trigger, s.valueNode, a, u, m, b, y, s.dir, r]);
    nt(() => v(), [v]);
    const [C, S] = g.useState();
    nt(() => {
        u && S(window.getComputedStyle(u).zIndex)
    }, [u]);
    const N = g.useCallback(E => {
        E && x.current === !0 && (v(), w == null || w(), x.current = !1)
    }, [v, w]);
    return c.jsx(CF, {
        scope: n,
        contentWrapper: a,
        shouldExpandOnScrollRef: h,
        onScrollButtonChange: N,
        children: c.jsx("div", {
            ref: l,
            style: {display: "flex", flexDirection: "column", position: "fixed", zIndex: C},
            children: c.jsx(re.div, {...o, ref: f, style: {boxSizing: "border-box", maxHeight: "100%", ...o.style}})
        })
    })
});
xN.displayName = wF;
var bF = "SelectPopperPosition", Mp = g.forwardRef((e, t) => {
    const {__scopeSelect: n, align: r = "start", collisionPadding: o = Qt, ...s} = e, i = qu(n);
    return c.jsx(wu, {
        ...i, ...s,
        ref: t,
        align: r,
        collisionPadding: o,
        style: {
            boxSizing: "border-box", ...s.style,
            "--radix-select-content-transform-origin": "var(--radix-popper-transform-origin)",
            "--radix-select-content-available-width": "var(--radix-popper-available-width)",
            "--radix-select-content-available-height": "var(--radix-popper-available-height)",
            "--radix-select-trigger-width": "var(--radix-popper-anchor-width)",
            "--radix-select-trigger-height": "var(--radix-popper-anchor-height)"
        }
    })
});
Mp.displayName = bF;
var [CF, jm] = ni(Io, {}), Rp = "SelectViewport", wN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, nonce: r, ...o} = e, s = so(Rp, n), i = jm(Rp, n), a = ge(t, s.onViewportChange),
        l = g.useRef(0);
    return c.jsxs(c.Fragment, {
        children: [c.jsx("style", {
            dangerouslySetInnerHTML: {__html: "[data-radix-select-viewport]{scrollbar-width:none;-ms-overflow-style:none;-webkit-overflow-scrolling:touch;}[data-radix-select-viewport]::-webkit-scrollbar{display:none}"},
            nonce: r
        }), c.jsx(Gu.Slot, {
            scope: n,
            children: c.jsx(re.div, {
                "data-radix-select-viewport": "",
                role: "presentation", ...o,
                ref: a,
                style: {position: "relative", flex: 1, overflow: "hidden auto", ...o.style},
                onScroll: J(o.onScroll, u => {
                    const d = u.currentTarget, {contentWrapper: f, shouldExpandOnScrollRef: p} = i;
                    if (p != null && p.current && f) {
                        const h = Math.abs(l.current - d.scrollTop);
                        if (h > 0) {
                            const x = window.innerHeight - Qt * 2, m = parseFloat(f.style.minHeight),
                                b = parseFloat(f.style.height), y = Math.max(m, b);
                            if (y < x) {
                                const w = y + h, v = Math.min(x, w), C = w - v;
                                f.style.height = v + "px", f.style.bottom === "0px" && (d.scrollTop = C > 0 ? C : 0, f.style.justifyContent = "flex-end")
                            }
                        }
                    }
                    l.current = d.scrollTop
                })
            })
        })]
    })
});
wN.displayName = Rp;
var bN = "SelectGroup", [SF, NF] = ni(bN), EF = g.forwardRef((e, t) => {
    const {__scopeSelect: n, ...r} = e, o = ct();
    return c.jsx(SF, {scope: n, id: o, children: c.jsx(re.div, {role: "group", "aria-labelledby": o, ...r, ref: t})})
});
EF.displayName = bN;
var CN = "SelectLabel", SN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, ...r} = e, o = NF(CN, n);
    return c.jsx(re.div, {id: o.id, ...r, ref: t})
});
SN.displayName = CN;
var Yc = "SelectItem", [kF, NN] = ni(Yc), EN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, value: r, disabled: o = !1, textValue: s, ...i} = e, a = oo(Yc, n), l = so(Yc, n),
        u = a.value === r, [d, f] = g.useState(s ?? ""), [p, h] = g.useState(!1), x = ge(t, w => {
            var v;
            return (v = l.itemRefCallback) == null ? void 0 : v.call(l, w, r, o)
        }), m = ct(), b = g.useRef("touch"), y = () => {
            o || (a.onValueChange(r), a.onOpenChange(!1))
        };
    if (r === "") throw new Error("A <Select.Item /> must have a value prop that is not an empty string. This is because the Select value can be set to an empty string to clear the selection and show the placeholder.");
    return c.jsx(kF, {
        scope: n,
        value: r,
        disabled: o,
        textId: m,
        isSelected: u,
        onItemTextChange: g.useCallback(w => {
            f(v => v || ((w == null ? void 0 : w.textContent) ?? "").trim())
        }, []),
        children: c.jsx(Gu.ItemSlot, {
            scope: n,
            value: r,
            disabled: o,
            textValue: d,
            children: c.jsx(re.div, {
                role: "option",
                "aria-labelledby": m,
                "data-highlighted": p ? "" : void 0,
                "aria-selected": u && p,
                "data-state": u ? "checked" : "unchecked",
                "aria-disabled": o || void 0,
                "data-disabled": o ? "" : void 0,
                tabIndex: o ? void 0 : -1, ...i,
                ref: x,
                onFocus: J(i.onFocus, () => h(!0)),
                onBlur: J(i.onBlur, () => h(!1)),
                onClick: J(i.onClick, () => {
                    b.current !== "mouse" && y()
                }),
                onPointerUp: J(i.onPointerUp, () => {
                    b.current === "mouse" && y()
                }),
                onPointerDown: J(i.onPointerDown, w => {
                    b.current = w.pointerType
                }),
                onPointerMove: J(i.onPointerMove, w => {
                    var v;
                    b.current = w.pointerType, o ? (v = l.onItemLeave) == null || v.call(l) : b.current === "mouse" && w.currentTarget.focus({preventScroll: !0})
                }),
                onPointerLeave: J(i.onPointerLeave, w => {
                    var v;
                    w.currentTarget === document.activeElement && ((v = l.onItemLeave) == null || v.call(l))
                }),
                onKeyDown: J(i.onKeyDown, w => {
                    var C;
                    ((C = l.searchRef) == null ? void 0 : C.current) !== "" && w.key === " " || (dF.includes(w.key) && y(), w.key === " " && w.preventDefault())
                })
            })
        })
    })
});
EN.displayName = Yc;
var _i = "SelectItemText", kN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, className: r, style: o, ...s} = e, i = oo(_i, n), a = so(_i, n), l = NN(_i, n),
        u = mF(_i, n), [d, f] = g.useState(null), p = ge(t, y => f(y), l.onItemTextChange, y => {
            var w;
            return (w = a.itemTextRefCallback) == null ? void 0 : w.call(a, y, l.value, l.disabled)
        }), h = d == null ? void 0 : d.textContent, x = g.useMemo(() => c.jsx("option", {
            value: l.value,
            disabled: l.disabled,
            children: h
        }, l.value), [l.disabled, l.value, h]), {onNativeOptionAdd: m, onNativeOptionRemove: b} = u;
    return nt(() => (m(x), () => b(x)), [m, b, x]), c.jsxs(c.Fragment, {
        children: [c.jsx(re.span, {
            id: l.textId, ...s,
            ref: p
        }), l.isSelected && i.valueNode && !i.valueNodeHasChildren ? Fo.createPortal(s.children, i.valueNode) : null]
    })
});
kN.displayName = _i;
var _N = "SelectItemIndicator", jN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, ...r} = e;
    return NN(_N, n).isSelected ? c.jsx(re.span, {"aria-hidden": !0, ...r, ref: t}) : null
});
jN.displayName = _N;
var Ap = "SelectScrollUpButton", PN = g.forwardRef((e, t) => {
    const n = so(Ap, e.__scopeSelect), r = jm(Ap, e.__scopeSelect), [o, s] = g.useState(!1),
        i = ge(t, r.onScrollButtonChange);
    return nt(() => {
        if (n.viewport && n.isPositioned) {
            let a = function () {
                const u = l.scrollTop > 0;
                s(u)
            };
            const l = n.viewport;
            return a(), l.addEventListener("scroll", a), () => l.removeEventListener("scroll", a)
        }
    }, [n.viewport, n.isPositioned]), o ? c.jsx(MN, {
        ...e, ref: i, onAutoScroll: () => {
            const {viewport: a, selectedItem: l} = n;
            a && l && (a.scrollTop = a.scrollTop - l.offsetHeight)
        }
    }) : null
});
PN.displayName = Ap;
var Ip = "SelectScrollDownButton", TN = g.forwardRef((e, t) => {
    const n = so(Ip, e.__scopeSelect), r = jm(Ip, e.__scopeSelect), [o, s] = g.useState(!1),
        i = ge(t, r.onScrollButtonChange);
    return nt(() => {
        if (n.viewport && n.isPositioned) {
            let a = function () {
                const u = l.scrollHeight - l.clientHeight, d = Math.ceil(l.scrollTop) < u;
                s(d)
            };
            const l = n.viewport;
            return a(), l.addEventListener("scroll", a), () => l.removeEventListener("scroll", a)
        }
    }, [n.viewport, n.isPositioned]), o ? c.jsx(MN, {
        ...e, ref: i, onAutoScroll: () => {
            const {viewport: a, selectedItem: l} = n;
            a && l && (a.scrollTop = a.scrollTop + l.offsetHeight)
        }
    }) : null
});
TN.displayName = Ip;
var MN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, onAutoScroll: r, ...o} = e, s = so("SelectScrollButton", n), i = g.useRef(null), a = Xu(n),
        l = g.useCallback(() => {
            i.current !== null && (window.clearInterval(i.current), i.current = null)
        }, []);
    return g.useEffect(() => () => l(), [l]), nt(() => {
        var d;
        const u = a().find(f => f.ref.current === document.activeElement);
        (d = u == null ? void 0 : u.ref.current) == null || d.scrollIntoView({block: "nearest"})
    }, [a]), c.jsx(re.div, {
        "aria-hidden": !0, ...o,
        ref: t,
        style: {flexShrink: 0, ...o.style},
        onPointerDown: J(o.onPointerDown, () => {
            i.current === null && (i.current = window.setInterval(r, 50))
        }),
        onPointerMove: J(o.onPointerMove, () => {
            var u;
            (u = s.onItemLeave) == null || u.call(s), i.current === null && (i.current = window.setInterval(r, 50))
        }),
        onPointerLeave: J(o.onPointerLeave, () => {
            l()
        })
    })
}), _F = "SelectSeparator", RN = g.forwardRef((e, t) => {
    const {__scopeSelect: n, ...r} = e;
    return c.jsx(re.div, {"aria-hidden": !0, ...r, ref: t})
});
RN.displayName = _F;
var Op = "SelectArrow", jF = g.forwardRef((e, t) => {
    const {__scopeSelect: n, ...r} = e, o = qu(n), s = oo(Op, n), i = so(Op, n);
    return s.open && i.position === "popper" ? c.jsx(bu, {...o, ...r, ref: t}) : null
});
jF.displayName = Op;

function AN(e) {
    return e === "" || e === void 0
}

var IN = g.forwardRef((e, t) => {
    const {value: n, ...r} = e, o = g.useRef(null), s = ge(t, o), i = cF(n);
    return g.useEffect(() => {
        const a = o.current, l = window.HTMLSelectElement.prototype,
            d = Object.getOwnPropertyDescriptor(l, "value").set;
        if (i !== n && d) {
            const f = new Event("change", {bubbles: !0});
            d.call(a, n), a.dispatchEvent(f)
        }
    }, [i, n]), c.jsx(_a, {asChild: !0, children: c.jsx("select", {...r, ref: s, defaultValue: n})})
});
IN.displayName = "BubbleSelect";

function ON(e) {
    const t = Xe(e), n = g.useRef(""), r = g.useRef(0), o = g.useCallback(i => {
        const a = n.current + i;
        t(a), function l(u) {
            n.current = u, window.clearTimeout(r.current), u !== "" && (r.current = window.setTimeout(() => l(""), 1e3))
        }(a)
    }, [t]), s = g.useCallback(() => {
        n.current = "", window.clearTimeout(r.current)
    }, []);
    return g.useEffect(() => () => window.clearTimeout(r.current), []), [n, o, s]
}

function DN(e, t, n) {
    const o = t.length > 1 && Array.from(t).every(u => u === t[0]) ? t[0] : t, s = n ? e.indexOf(n) : -1;
    let i = PF(e, Math.max(s, 0));
    o.length === 1 && (i = i.filter(u => u !== n));
    const l = i.find(u => u.textValue.toLowerCase().startsWith(o.toLowerCase()));
    return l !== n ? l : void 0
}

function PF(e, t) {
    return e.map((n, r) => e[(t + r) % e.length])
}

var TF = cN, $N = dN, MF = pN, RF = hN, AF = mN, LN = gN, IF = wN, FN = SN, zN = EN, OF = kN, DF = jN, BN = PN, VN = TN,
    HN = RN;
const un = TF, dn = MF, Ut = g.forwardRef(({className: e, children: t, ...n}, r) => c.jsxs($N, {
    ref: r,
    className: se("flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1", e), ...n,
    children: [t, c.jsx(RF, {asChild: !0, children: c.jsx(Jw, {className: "h-4 w-4 opacity-50"})})]
}));
Ut.displayName = $N.displayName;
const UN = g.forwardRef(({className: e, ...t}, n) => c.jsx(BN, {
    ref: n,
    className: se("flex cursor-default items-center justify-center py-1", e), ...t,
    children: c.jsx(Uj, {className: "h-4 w-4"})
}));
UN.displayName = BN.displayName;
const WN = g.forwardRef(({className: e, ...t}, n) => c.jsx(VN, {
    ref: n,
    className: se("flex cursor-default items-center justify-center py-1", e), ...t,
    children: c.jsx(Jw, {className: "h-4 w-4"})
}));
WN.displayName = VN.displayName;
const Wt = g.forwardRef(({
                             className: e,
                             children: t,
                             position: n = "popper",
                             ...r
                         }, o) => c.jsx(AF, {
    children: c.jsxs(LN, {
        ref: o,
        className: se("relative z-50 max-h-96 min-w-[8rem] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2", n === "popper" && "data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1", e),
        position: n, ...r,
        children: [c.jsx(UN, {}), c.jsx(IF, {
            className: se("p-1", n === "popper" && "h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)]"),
            children: t
        }), c.jsx(WN, {})]
    })
}));
Wt.displayName = LN.displayName;
const $F = g.forwardRef(({className: e, ...t}, n) => c.jsx(FN, {
    ref: n,
    className: se("py-1.5 pl-8 pr-2 text-sm font-semibold", e), ...t
}));
$F.displayName = FN.displayName;
const je = g.forwardRef(({className: e, children: t, ...n}, r) => c.jsxs(zN, {
    ref: r,
    className: se("relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50", e), ...n,
    children: [c.jsx("span", {
        className: "absolute left-2 flex h-3.5 w-3.5 items-center justify-center",
        children: c.jsx(DF, {children: c.jsx(zh, {className: "h-4 w-4"})})
    }), c.jsx(OF, {children: t})]
}));
je.displayName = zN.displayName;
const LF = g.forwardRef(({className: e, ...t}, n) => c.jsx(HN, {
    ref: n,
    className: se("-mx-1 my-1 h-px bg-muted", e), ...t
}));
LF.displayName = HN.displayName;
const FF = ({id: e, data: t}) => {
    const [n, r] = g.useState(!1), [o, s] = g.useState(t.label), [i, a] = g.useState(t.settings || {}), {deleteNode: l} = rt(),
        u = () => {
            switch (t.type) {
                case"wait":
                    return c.jsx(Bh, {className: "h-4 w-4 text-orange-600"});
                case"branch":
                    return c.jsx(t1, {className: "h-4 w-4 text-purple-600"});
                default:
                    return null
            }
        }, d = x => {
            s(x.target.value)
        }, f = (x, m) => {
            a({...i, [x]: m})
        }, p = () => t.type === "wait" ? c.jsxs(c.Fragment, {
            children: [c.jsx(de, {
                type: "target",
                position: q.Left,
                className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
            }), c.jsx(de, {type: "source", position: q.Right, className: "!bg-gray-300 !w-3 !h-3 !rounded-full"})]
        }) : c.jsxs(c.Fragment, {
            children: [c.jsx(de, {type: "target", position: q.Top}), c.jsx(de, {
                type: "source",
                position: q.Bottom
            })]
        }), h = () => {
            switch (t.type) {
                case"message":
                    return c.jsx("div", {
                        className: "pt-2",
                        children: c.jsx(De, {
                            type: "text",
                            placeholder: "Type your message...",
                            value: i.message || "",
                            onChange: x => f("message", x.target.value),
                            className: "mt-1"
                        })
                    });
                case"wait":
                    return c.jsx("div", {
                        className: "pt-2 space-y-2",
                        children: c.jsxs("div", {
                            className: "flex gap-2",
                            children: [c.jsx(De, {
                                type: "number",
                                placeholder: "Duration",
                                value: i.waitTime || "",
                                onChange: x => f("waitTime", parseInt(x.target.value)),
                                className: "flex-1",
                                min: 0
                            }), c.jsxs(un, {
                                value: i.waitUnit || "seconds",
                                onValueChange: x => f("waitUnit", x),
                                children: [c.jsx(Ut, {
                                    className: "w-[110px]",
                                    children: c.jsx(dn, {placeholder: "Unit"})
                                }), c.jsxs(Wt, {
                                    children: [c.jsx(je, {
                                        value: "seconds",
                                        children: "Seconds"
                                    }), c.jsx(je, {value: "minutes", children: "Minutes"}), c.jsx(je, {
                                        value: "hours",
                                        children: "Hours"
                                    }), c.jsx(je, {value: "days", children: "Days"})]
                                })]
                            })]
                        })
                    });
                case"branch":
                    return c.jsxs("div", {
                        className: "pt-2",
                        children: [c.jsx(De, {
                            type: "text", placeholder: "Add a condition...", onKeyPress: x => {
                                if (x.key === "Enter") {
                                    const m = i.conditions || [];
                                    f("conditions", [...m, x.target.value]), x.target.value = ""
                                }
                            }, className: "mb-2"
                        }), (i.conditions || []).map((x, m) => c.jsxs("div", {
                            className: "text-sm py-1 flex items-center justify-between",
                            children: [c.jsx("span", {children: x}), c.jsx("button", {
                                onClick: () => {
                                    const b = [...i.conditions || []];
                                    b.splice(m, 1), f("conditions", b)
                                }, className: "text-gray-400 hover:text-gray-600", children: "×"
                            })]
                        }, m))]
                    });
                default:
                    return null
            }
        };
    return c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: "bg-white rounded-lg shadow-md p-3 min-w-[200px]",
                children: [p(), c.jsxs("div", {
                    className: "space-y-2",
                    children: [n ? c.jsx(De, {
                        type: "text",
                        value: o,
                        onChange: d,
                        onBlur: () => r(!1),
                        onKeyPress: x => {
                            x.key === "Enter" && r(!1)
                        },
                        autoFocus: !0
                    }) : c.jsxs("div", {
                        className: "flex items-center gap-2",
                        children: [u(), c.jsx("div", {
                            className: "font-medium cursor-pointer",
                            onClick: () => r(!0),
                            children: o
                        })]
                    }), h()]
                })]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => l(e),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, zF = ({id: e, data: t}) => c.jsx(lr, {
    id: e,
    title: t.label,
    icon: c.jsx(o1, {className: "h-4 w-4 text-red-600"}),
    headerBackground: "bg-gray-50",
    borderColor: "border-gray-100",
    handles: {left: !0},
    children: c.jsx("div", {
        className: "text-sm text-gray-600",
        children: c.jsx("p", {children: "This marks the end of your flow."})
    })
}), BF = ({id: e, data: t}) => c.jsx(lr, {
    id: e,
    title: "En mensaje",
    icon: c.jsx(ja, {className: "h-4 w-4 text-green-600"}),
    headerBackground: "bg-gray-50",
    borderColor: "border-gray-100",
    handles: {right: !0},
    children: c.jsxs("div", {
        className: "text-sm text-gray-600",
        children: [c.jsxs("p", {children: ["Este flujo se activará cada vez que un cliente envíe ", c.jsx("strong", {children: "any message"}), " a tú bot."]}), c.jsx("p", {
            className: "mt-2 text-xs text-gray-500",
            children: "Utilice esto como punto de partida para conversaciones generales."
        })]
    })
}), VF = ({
              id: e,
              value: t,
              matchType: n,
              index: r,
              totalKeywords: o,
              onUpdate: s,
              onRemove: i
          }) => c.jsxs("div", {
    className: "flex items-center gap-2 relative group",
    children: [c.jsx(De, {
        value: t,
        onChange: a => s(e, "value", a.target.value),
        placeholder: "Enter keyword",
        className: "flex-1"
    }), c.jsxs(un, {
        value: n,
        onValueChange: a => s(e, "matchType", a),
        children: [c.jsx(Ut, {
            className: "w-[140px]",
            children: c.jsx(dn, {placeholder: "Exact match"})
        }), c.jsxs(Wt, {
            children: [c.jsx(je, {value: "exact", children: "Exact match"}), c.jsx(je, {
                value: "contains",
                children: "Contains"
            })]
        })]
    }), c.jsx(le, {
        variant: "ghost",
        size: "icon",
        onClick: () => i(e),
        className: "text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity",
        children: c.jsx(at, {className: "h-4 w-4"})
    }), c.jsx(de, {type: "source", position: q.Right, id: `keyword-${e}`, style: {right: "-4px", background: "#555"}})]
}), HF = ({id: e, data: t}) => {
    const {setNodes: n} = $t(), [r, o] = g.useState(t.keywords || []);
    g.useEffect(() => {
        n(l => l.map(u => u.id === e ? {...u, data: {...u.data, keywords: r}} : u))
    }, [r, e, n]);
    const s = () => {
        o([...r, {id: Math.random().toString(), value: "", matchType: "exact"}])
    }, i = l => {
        o(r.filter(u => u.id !== l))
    }, a = (l, u, d) => {
        o(r.map(f => f.id === l ? {...f, [u]: d} : f))
    };
    return c.jsxs(lr, {
        id: e,
        title: "Activador de palabra clave",
        icon: c.jsx(r1, {className: "h-4 w-4 text-yellow-500"}),
        headerBackground: "bg-gray-50",
        borderColor: "border-gray-100",
        nodeWidth: "w-[350px]",
        handles: {left: !0},
        children: [c.jsx("div", {
            className: "text-sm text-gray-700 mb-3",
            children: c.jsxs("p", {children: ["Este flujo se activará cuando se cumplan ciertas condiciones ", c.jsx("strong", {children: "keywords"}), " se detectan en los mensajes de los usuarios."]})
        }), c.jsx("div", {
            className: "space-y-3",
            children: r.map((l, u) => c.jsx(VF, {
                ...l,
                index: u,
                totalKeywords: r.length,
                onUpdate: a,
                onRemove: i
            }, l.id))
        }), r.length === 0 && c.jsx("div", {
            className: "text-sm text-gray-500 mb-3",
            children: "Add a keyword below to start capturing specific user inputs."
        }), c.jsxs(le, {
            variant: "outline",
            size: "sm",
            onClick: s,
            className: "w-full flex items-center justify-center mt-3",
            children: [c.jsx(Pa, {className: "h-4 w-4 mr-2"}), "Add Keyword"]
        })]
    })
}, UF = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"], WF = ({data: e, id: t}) => {
    var a;
    const {setNodes: n} = $t(), [r, o] = g.useState(((a = e.settings) == null ? void 0 : a.timeRanges) || []);
    g.useEffect(() => {
        n(l => l.map(u => u.id === t ? {...u, data: {...u.data, settings: {...u.data.settings, timeRanges: r}}} : u))
    }, [r, t, n]);
    const s = l => {
        o(r.filter((u, d) => d !== l))
    }, i = (l, u, d) => {
        const f = [...r];
        f[l] = {...f[l], [u]: d}, o(f)
    };
    return c.jsxs("div", {
        className: "bg-white rounded-lg shadow-lg w-[350px]",
        children: [c.jsx(de, {
            type: "target",
            position: q.Left,
            style: {left: "-4px", background: "#555"}
        }), c.jsxs("div", {
            className: "flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50",
            children: [c.jsx(Bh, {className: "h-4 w-4 text-orange-600"}), c.jsx("div", {
                className: "font-medium",
                children: "Opening Hours"
            })]
        }), c.jsx("div", {
            className: "space-y-4", children: r.map((l, u) => c.jsxs("div", {
                className: "flex gap-2 items-end",
                children: [c.jsxs("div", {
                    className: "flex-1",
                    children: [c.jsx("label", {
                        className: "text-sm text-gray-500",
                        children: "Day"
                    }), c.jsxs(un, {
                        value: l.day,
                        onValueChange: d => i(u, "day", d),
                        children: [c.jsx(Ut, {children: c.jsx(dn, {placeholder: "Select day"})}), c.jsx(Wt, {
                            children: UF.map(d => c.jsx(je, {
                                value: d,
                                children: d
                            }, d))
                        })]
                    })]
                }), c.jsxs("div", {
                    className: "flex-1",
                    children: [c.jsx("label", {
                        className: "text-sm text-gray-500",
                        children: "Opens"
                    }), c.jsxs(un, {
                        value: l.start,
                        onValueChange: d => i(u, "start", d),
                        children: [c.jsx(Ut, {children: c.jsx(dn, {placeholder: "Start time"})}), c.jsx(Wt, {
                            children: Array.from({length: 24}).map((d, f) => c.jsx(je, {
                                value: `${f.toString().padStart(2, "0")}:00`,
                                children: `${f.toString().padStart(2, "0")}:00`
                            }, f))
                        })]
                    })]
                }), c.jsxs("div", {
                    className: "flex-1",
                    children: [c.jsx("label", {
                        className: "text-sm text-gray-500",
                        children: "Closes"
                    }), c.jsxs(un, {
                        value: l.end,
                        onValueChange: d => i(u, "end", d),
                        children: [c.jsx(Ut, {children: c.jsx(dn, {placeholder: "End time"})}), c.jsx(Wt, {
                            children: Array.from({length: 24}).map((d, f) => c.jsx(je, {
                                value: `${f.toString().padStart(2, "0")}:00`,
                                children: `${f.toString().padStart(2, "0")}:00`
                            }, f))
                        })]
                    })]
                }), c.jsx(le, {
                    variant: "ghost",
                    size: "icon",
                    onClick: () => s(u),
                    className: "mb-[2px]",
                    children: c.jsx(Zs, {className: "w-4 h-4"})
                })]
            }, u))
        }), c.jsx(de, {
            type: "source",
            position: q.Right,
            id: "open",
            style: {right: "-4px", top: "30%", background: "#22c55e"}
        }), c.jsx(de, {
            type: "source",
            position: q.Right,
            id: "closed",
            style: {right: "-4px", top: "70%", background: "#ef4444"}
        })]
    })
};
var KF = "Label", KN = g.forwardRef((e, t) => c.jsx(re.label, {
    ...e, ref: t, onMouseDown: n => {
        var o;
        n.target.closest("button, input, select, textarea") || ((o = e.onMouseDown) == null || o.call(e, n), !n.defaultPrevented && n.detail > 1 && n.preventDefault())
    }
}));
KN.displayName = KF;
var YN = KN;
const YF = mu("text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"),
    Se = g.forwardRef(({className: e, ...t}, n) => c.jsx(YN, {ref: n, className: se(YF(), e), ...t}));
Se.displayName = YN.displayName;
const X0 = window.data.templates;
var q0 = 1, GF = .9, XF = .8, qF = .17, sf = .1, af = .999, QF = .9999, ZF = .99, JF = /[\\\/_+.#"@\[\(\{&]/,
    e6 = /[\\\/_+.#"@\[\(\{&]/g, t6 = /[\s-]/, GN = /[\s-]/g;

function Dp(e, t, n, r, o, s, i) {
    if (s === t.length) return o === e.length ? q0 : ZF;
    var a = `${o},${s}`;
    if (i[a] !== void 0) return i[a];
    for (var l = r.charAt(s), u = n.indexOf(l, o), d = 0, f, p, h, x; u >= 0;) f = Dp(e, t, n, r, u + 1, s + 1, i), f > d && (u === o ? f *= q0 : JF.test(e.charAt(u - 1)) ? (f *= XF, h = e.slice(o, u - 1).match(e6), h && o > 0 && (f *= Math.pow(af, h.length))) : t6.test(e.charAt(u - 1)) ? (f *= GF, x = e.slice(o, u - 1).match(GN), x && o > 0 && (f *= Math.pow(af, x.length))) : (f *= qF, o > 0 && (f *= Math.pow(af, u - o))), e.charAt(u) !== t.charAt(s) && (f *= QF)), (f < sf && n.charAt(u - 1) === r.charAt(s + 1) || r.charAt(s + 1) === r.charAt(s) && n.charAt(u - 1) !== r.charAt(s)) && (p = Dp(e, t, n, r, u + 1, s + 2, i), p * sf > f && (f = p * sf)), f > d && (d = f), u = n.indexOf(l, u + 1);
    return i[a] = d, d
}

function Q0(e) {
    return e.toLowerCase().replace(GN, " ")
}

function n6(e, t, n) {
    return e = n && n.length > 0 ? `${e + " " + n.join(" ")}` : e, Dp(e, t, Q0(e), Q0(t), 0, 0, {})
}

var Pm = "Dialog", [XN, Ez] = mn(Pm), [r6, vn] = XN(Pm), qN = e => {
    const {__scopeDialog: t, children: n, open: r, defaultOpen: o, onOpenChange: s, modal: i = !0} = e,
        a = g.useRef(null), l = g.useRef(null), [u = !1, d] = To({prop: r, defaultProp: o, onChange: s});
    return c.jsx(r6, {
        scope: t,
        triggerRef: a,
        contentRef: l,
        contentId: ct(),
        titleId: ct(),
        descriptionId: ct(),
        open: u,
        onOpenChange: d,
        onOpenToggle: g.useCallback(() => d(f => !f), [d]),
        modal: i,
        children: n
    })
};
qN.displayName = Pm;
var QN = "DialogTrigger", o6 = g.forwardRef((e, t) => {
    const {__scopeDialog: n, ...r} = e, o = vn(QN, n), s = ge(t, o.triggerRef);
    return c.jsx(re.button, {
        type: "button",
        "aria-haspopup": "dialog",
        "aria-expanded": o.open,
        "aria-controls": o.contentId,
        "data-state": Rm(o.open), ...r,
        ref: s,
        onClick: J(e.onClick, o.onOpenToggle)
    })
});
o6.displayName = QN;
var Tm = "DialogPortal", [s6, ZN] = XN(Tm, {forceMount: void 0}), JN = e => {
    const {__scopeDialog: t, forceMount: n, children: r, container: o} = e, s = vn(Tm, t);
    return c.jsx(s6, {
        scope: t,
        forceMount: n,
        children: g.Children.map(r, i => c.jsx(Dt, {
            present: n || s.open,
            children: c.jsx(Qs, {asChild: !0, container: o, children: i})
        }))
    })
};
JN.displayName = Tm;
var Gc = "DialogOverlay", eE = g.forwardRef((e, t) => {
    const n = ZN(Gc, e.__scopeDialog), {forceMount: r = n.forceMount, ...o} = e, s = vn(Gc, e.__scopeDialog);
    return s.modal ? c.jsx(Dt, {present: r || s.open, children: c.jsx(a6, {...o, ref: t})}) : null
});
eE.displayName = Gc;
var i6 = Kr("DialogOverlay.RemoveScroll"), a6 = g.forwardRef((e, t) => {
    const {__scopeDialog: n, ...r} = e, o = vn(Gc, n);
    return c.jsx(La, {
        as: i6,
        allowPinchZoom: !0,
        shards: [o.contentRef],
        children: c.jsx(re.div, {"data-state": Rm(o.open), ...r, ref: t, style: {pointerEvents: "auto", ...r.style}})
    })
}), Oo = "DialogContent", tE = g.forwardRef((e, t) => {
    const n = ZN(Oo, e.__scopeDialog), {forceMount: r = n.forceMount, ...o} = e, s = vn(Oo, e.__scopeDialog);
    return c.jsx(Dt, {present: r || s.open, children: s.modal ? c.jsx(l6, {...o, ref: t}) : c.jsx(c6, {...o, ref: t})})
});
tE.displayName = Oo;
var l6 = g.forwardRef((e, t) => {
    const n = vn(Oo, e.__scopeDialog), r = g.useRef(null), o = ge(t, n.contentRef, r);
    return g.useEffect(() => {
        const s = r.current;
        if (s) return Bu(s)
    }, []), c.jsx(nE, {
        ...e,
        ref: o,
        trapFocus: n.open,
        disableOutsidePointerEvents: !0,
        onCloseAutoFocus: J(e.onCloseAutoFocus, s => {
            var i;
            s.preventDefault(), (i = n.triggerRef.current) == null || i.focus()
        }),
        onPointerDownOutside: J(e.onPointerDownOutside, s => {
            const i = s.detail.originalEvent, a = i.button === 0 && i.ctrlKey === !0;
            (i.button === 2 || a) && s.preventDefault()
        }),
        onFocusOutside: J(e.onFocusOutside, s => s.preventDefault())
    })
}), c6 = g.forwardRef((e, t) => {
    const n = vn(Oo, e.__scopeDialog), r = g.useRef(!1), o = g.useRef(!1);
    return c.jsx(nE, {
        ...e, ref: t, trapFocus: !1, disableOutsidePointerEvents: !1, onCloseAutoFocus: s => {
            var i, a;
            (i = e.onCloseAutoFocus) == null || i.call(e, s), s.defaultPrevented || (r.current || (a = n.triggerRef.current) == null || a.focus(), s.preventDefault()), r.current = !1, o.current = !1
        }, onInteractOutside: s => {
            var l, u;
            (l = e.onInteractOutside) == null || l.call(e, s), s.defaultPrevented || (r.current = !0, s.detail.originalEvent.type === "pointerdown" && (o.current = !0));
            const i = s.target;
            ((u = n.triggerRef.current) == null ? void 0 : u.contains(i)) && s.preventDefault(), s.detail.originalEvent.type === "focusin" && o.current && s.preventDefault()
        }
    })
}), nE = g.forwardRef((e, t) => {
    const {__scopeDialog: n, trapFocus: r, onOpenAutoFocus: o, onCloseAutoFocus: s, ...i} = e, a = vn(Oo, n),
        l = g.useRef(null), u = ge(t, l);
    return zu(), c.jsxs(c.Fragment, {
        children: [c.jsx($a, {
            asChild: !0,
            loop: !0,
            trapped: r,
            onMountAutoFocus: o,
            onUnmountAutoFocus: s,
            children: c.jsx(zo, {
                role: "dialog",
                id: a.contentId,
                "aria-describedby": a.descriptionId,
                "aria-labelledby": a.titleId,
                "data-state": Rm(a.open), ...i,
                ref: u,
                onDismiss: () => a.onOpenChange(!1)
            })
        }), c.jsxs(c.Fragment, {
            children: [c.jsx(u6, {titleId: a.titleId}), c.jsx(f6, {
                contentRef: l,
                descriptionId: a.descriptionId
            })]
        })]
    })
}), Mm = "DialogTitle", rE = g.forwardRef((e, t) => {
    const {__scopeDialog: n, ...r} = e, o = vn(Mm, n);
    return c.jsx(re.h2, {id: o.titleId, ...r, ref: t})
});
rE.displayName = Mm;
var oE = "DialogDescription", sE = g.forwardRef((e, t) => {
    const {__scopeDialog: n, ...r} = e, o = vn(oE, n);
    return c.jsx(re.p, {id: o.descriptionId, ...r, ref: t})
});
sE.displayName = oE;
var iE = "DialogClose", aE = g.forwardRef((e, t) => {
    const {__scopeDialog: n, ...r} = e, o = vn(iE, n);
    return c.jsx(re.button, {type: "button", ...r, ref: t, onClick: J(e.onClick, () => o.onOpenChange(!1))})
});
aE.displayName = iE;

function Rm(e) {
    return e ? "open" : "closed"
}

var lE = "DialogTitleWarning", [kz, cE] = X_(lE, {contentName: Oo, titleName: Mm, docsSlug: "dialog"}),
    u6 = ({titleId: e}) => {
        const t = cE(lE), n = `\`${t.contentName}\` requires a \`${t.titleName}\` for the component to be accessible for screen reader users.

If you want to hide the \`${t.titleName}\`, you can wrap it with our VisuallyHidden component.

For more information, see https://radix-ui.com/primitives/docs/components/${t.docsSlug}`;
        return g.useEffect(() => {
            e && (document.getElementById(e) || console.error(n))
        }, [n, e]), null
    }, d6 = "DialogDescriptionWarning", f6 = ({contentRef: e, descriptionId: t}) => {
        const r = `Warning: Missing \`Description\` or \`aria-describedby={undefined}\` for {${cE(d6).contentName}}.`;
        return g.useEffect(() => {
            var s;
            const o = (s = e.current) == null ? void 0 : s.getAttribute("aria-describedby");
            t && o && (document.getElementById(t) || console.warn(r))
        }, [r, e, t]), null
    }, Am = qN, Im = JN, Ha = eE, Ua = tE, Qu = rE, Zu = sE, uE = aE, xi = '[cmdk-group=""]', lf = '[cmdk-group-items=""]',
    p6 = '[cmdk-group-heading=""]', dE = '[cmdk-item=""]', Z0 = `${dE}:not([aria-disabled="true"])`,
    $p = "cmdk-item-select", Xo = "data-value", h6 = (e, t, n) => n6(e, t, n), fE = g.createContext(void 0),
    Wa = () => g.useContext(fE), pE = g.createContext(void 0), Om = () => g.useContext(pE),
    hE = g.createContext(void 0), mE = g.forwardRef((e, t) => {
        let n = qo(() => {
            var I, V;
            return {
                search: "",
                value: (V = (I = e.value) != null ? I : e.defaultValue) != null ? V : "",
                selectedItemId: void 0,
                filtered: {count: 0, items: new Map, groups: new Set}
            }
        }), r = qo(() => new Set), o = qo(() => new Map), s = qo(() => new Map), i = qo(() => new Set), a = gE(e), {
            label: l,
            children: u,
            value: d,
            onValueChange: f,
            filter: p,
            shouldFilter: h,
            loop: x,
            disablePointerSelection: m = !1,
            vimBindings: b = !0,
            ...y
        } = e, w = ct(), v = ct(), C = ct(), S = g.useRef(null), N = E6();
        Do(() => {
            if (d !== void 0) {
                let I = d.trim();
                n.current.value = I, E.emit()
            }
        }, [d]), Do(() => {
            N(6, B)
        }, []);
        let E = g.useMemo(() => ({
            subscribe: I => (i.current.add(I), () => i.current.delete(I)),
            snapshot: () => n.current,
            setState: (I, V, H) => {
                var U, K, F, Y;
                if (!Object.is(n.current[I], V)) {
                    if (n.current[I] = V, I === "search") $(), D(), N(1, L); else if (I === "value") {
                        if (document.activeElement.hasAttribute("cmdk-input") || document.activeElement.hasAttribute("cmdk-root")) {
                            let X = document.getElementById(C);
                            X ? X.focus() : (U = document.getElementById(w)) == null || U.focus()
                        }
                        if (N(7, () => {
                            var X;
                            n.current.selectedItemId = (X = k()) == null ? void 0 : X.id, E.emit()
                        }), H || N(5, B), ((K = a.current) == null ? void 0 : K.value) !== void 0) {
                            let X = V ?? "";
                            (Y = (F = a.current).onValueChange) == null || Y.call(F, X);
                            return
                        }
                    }
                    E.emit()
                }
            },
            emit: () => {
                i.current.forEach(I => I())
            }
        }), []), T = g.useMemo(() => ({
            value: (I, V, H) => {
                var U;
                V !== ((U = s.current.get(I)) == null ? void 0 : U.value) && (s.current.set(I, {
                    value: V,
                    keywords: H
                }), n.current.filtered.items.set(I, O(V, H)), N(2, () => {
                    D(), E.emit()
                }))
            },
            item: (I, V) => (r.current.add(I), V && (o.current.has(V) ? o.current.get(V).add(I) : o.current.set(V, new Set([I]))), N(3, () => {
                $(), D(), n.current.value || L(), E.emit()
            }), () => {
                s.current.delete(I), r.current.delete(I), n.current.filtered.items.delete(I);
                let H = k();
                N(4, () => {
                    $(), (H == null ? void 0 : H.getAttribute("id")) === I && L(), E.emit()
                })
            }),
            group: I => (o.current.has(I) || o.current.set(I, new Set), () => {
                s.current.delete(I), o.current.delete(I)
            }),
            filter: () => a.current.shouldFilter,
            label: l || e["aria-label"],
            getDisablePointerSelection: () => a.current.disablePointerSelection,
            listId: w,
            inputId: C,
            labelId: v,
            listInnerRef: S
        }), []);

        function O(I, V) {
            var H, U;
            let K = (U = (H = a.current) == null ? void 0 : H.filter) != null ? U : h6;
            return I ? K(I, n.current.search, V) : 0
        }

        function D() {
            if (!n.current.search || a.current.shouldFilter === !1) return;
            let I = n.current.filtered.items, V = [];
            n.current.filtered.groups.forEach(U => {
                let K = o.current.get(U), F = 0;
                K.forEach(Y => {
                    let X = I.get(Y);
                    F = Math.max(X, F)
                }), V.push([U, F])
            });
            let H = S.current;
            M().sort((U, K) => {
                var F, Y;
                let X = U.getAttribute("id"), te = K.getAttribute("id");
                return ((F = I.get(te)) != null ? F : 0) - ((Y = I.get(X)) != null ? Y : 0)
            }).forEach(U => {
                let K = U.closest(lf);
                K ? K.appendChild(U.parentElement === K ? U : U.closest(`${lf} > *`)) : H.appendChild(U.parentElement === H ? U : U.closest(`${lf} > *`))
            }), V.sort((U, K) => K[1] - U[1]).forEach(U => {
                var K;
                let F = (K = S.current) == null ? void 0 : K.querySelector(`${xi}[${Xo}="${encodeURIComponent(U[0])}"]`);
                F == null || F.parentElement.appendChild(F)
            })
        }

        function L() {
            let I = M().find(H => H.getAttribute("aria-disabled") !== "true"), V = I == null ? void 0 : I.getAttribute(Xo);
            E.setState("value", V || void 0)
        }

        function $() {
            var I, V, H, U;
            if (!n.current.search || a.current.shouldFilter === !1) {
                n.current.filtered.count = r.current.size;
                return
            }
            n.current.filtered.groups = new Set;
            let K = 0;
            for (let F of r.current) {
                let Y = (V = (I = s.current.get(F)) == null ? void 0 : I.value) != null ? V : "",
                    X = (U = (H = s.current.get(F)) == null ? void 0 : H.keywords) != null ? U : [], te = O(Y, X);
                n.current.filtered.items.set(F, te), te > 0 && K++
            }
            for (let [F, Y] of o.current) for (let X of Y) if (n.current.filtered.items.get(X) > 0) {
                n.current.filtered.groups.add(F);
                break
            }
            n.current.filtered.count = K
        }

        function B() {
            var I, V, H;
            let U = k();
            U && (((I = U.parentElement) == null ? void 0 : I.firstChild) === U && ((H = (V = U.closest(xi)) == null ? void 0 : V.querySelector(p6)) == null || H.scrollIntoView({block: "nearest"})), U.scrollIntoView({block: "nearest"}))
        }

        function k() {
            var I;
            return (I = S.current) == null ? void 0 : I.querySelector(`${dE}[aria-selected="true"]`)
        }

        function M() {
            var I;
            return Array.from(((I = S.current) == null ? void 0 : I.querySelectorAll(Z0)) || [])
        }

        function j(I) {
            let V = M()[I];
            V && E.setState("value", V.getAttribute(Xo))
        }

        function A(I) {
            var V;
            let H = k(), U = M(), K = U.findIndex(Y => Y === H), F = U[K + I];
            (V = a.current) != null && V.loop && (F = K + I < 0 ? U[U.length - 1] : K + I === U.length ? U[0] : U[K + I]), F && E.setState("value", F.getAttribute(Xo))
        }

        function P(I) {
            let V = k(), H = V == null ? void 0 : V.closest(xi), U;
            for (; H && !U;) H = I > 0 ? S6(H, xi) : N6(H, xi), U = H == null ? void 0 : H.querySelector(Z0);
            U ? E.setState("value", U.getAttribute(Xo)) : A(I)
        }

        let _ = () => j(M().length - 1), R = I => {
            I.preventDefault(), I.metaKey ? _() : I.altKey ? P(1) : A(1)
        }, z = I => {
            I.preventDefault(), I.metaKey ? j(0) : I.altKey ? P(-1) : A(-1)
        };
        return g.createElement(re.div, {
            ref: t, tabIndex: -1, ...y, "cmdk-root": "", onKeyDown: I => {
                var V;
                (V = y.onKeyDown) == null || V.call(y, I);
                let H = I.nativeEvent.isComposing || I.keyCode === 229;
                if (!(I.defaultPrevented || H)) switch (I.key) {
                    case"n":
                    case"j": {
                        b && I.ctrlKey && R(I);
                        break
                    }
                    case"ArrowDown": {
                        R(I);
                        break
                    }
                    case"p":
                    case"k": {
                        b && I.ctrlKey && z(I);
                        break
                    }
                    case"ArrowUp": {
                        z(I);
                        break
                    }
                    case"Home": {
                        I.preventDefault(), j(0);
                        break
                    }
                    case"End": {
                        I.preventDefault(), _();
                        break
                    }
                    case"Enter": {
                        I.preventDefault();
                        let U = k();
                        if (U) {
                            let K = new Event($p);
                            U.dispatchEvent(K)
                        }
                    }
                }
            }
        }, g.createElement("label", {
            "cmdk-label": "",
            htmlFor: T.inputId,
            id: T.labelId,
            style: _6
        }, l), Ju(e, I => g.createElement(pE.Provider, {value: E}, g.createElement(fE.Provider, {value: T}, I))))
    }), m6 = g.forwardRef((e, t) => {
        var n, r;
        let o = ct(), s = g.useRef(null), i = g.useContext(hE), a = Wa(), l = gE(e),
            u = (r = (n = l.current) == null ? void 0 : n.forceMount) != null ? r : i == null ? void 0 : i.forceMount;
        Do(() => {
            if (!u) return a.item(o, i == null ? void 0 : i.id)
        }, [u]);
        let d = vE(o, s, [e.value, e.children, s], e.keywords), f = Om(), p = qr(N => N.value && N.value === d.current),
            h = qr(N => u || a.filter() === !1 ? !0 : N.search ? N.filtered.items.get(o) > 0 : !0);
        g.useEffect(() => {
            let N = s.current;
            if (!(!N || e.disabled)) return N.addEventListener($p, x), () => N.removeEventListener($p, x)
        }, [h, e.onSelect, e.disabled]);

        function x() {
            var N, E;
            m(), (E = (N = l.current).onSelect) == null || E.call(N, d.current)
        }

        function m() {
            f.setState("value", d.current, !0)
        }

        if (!h) return null;
        let {disabled: b, value: y, onSelect: w, forceMount: v, keywords: C, ...S} = e;
        return g.createElement(re.div, {
            ref: Wr(s, t), ...S,
            id: o,
            "cmdk-item": "",
            role: "option",
            "aria-disabled": !!b,
            "aria-selected": !!p,
            "data-disabled": !!b,
            "data-selected": !!p,
            onPointerMove: b || a.getDisablePointerSelection() ? void 0 : m,
            onClick: b ? void 0 : x
        }, e.children)
    }), g6 = g.forwardRef((e, t) => {
        let {heading: n, children: r, forceMount: o, ...s} = e, i = ct(), a = g.useRef(null), l = g.useRef(null), u = ct(),
            d = Wa(), f = qr(h => o || d.filter() === !1 ? !0 : h.search ? h.filtered.groups.has(i) : !0);
        Do(() => d.group(i), []), vE(i, a, [e.value, e.heading, l]);
        let p = g.useMemo(() => ({id: i, forceMount: o}), [o]);
        return g.createElement(re.div, {
            ref: Wr(a, t), ...s,
            "cmdk-group": "",
            role: "presentation",
            hidden: f ? void 0 : !0
        }, n && g.createElement("div", {
            ref: l,
            "cmdk-group-heading": "",
            "aria-hidden": !0,
            id: u
        }, n), Ju(e, h => g.createElement("div", {
            "cmdk-group-items": "",
            role: "group",
            "aria-labelledby": n ? u : void 0
        }, g.createElement(hE.Provider, {value: p}, h))))
    }), v6 = g.forwardRef((e, t) => {
        let {alwaysRender: n, ...r} = e, o = g.useRef(null), s = qr(i => !i.search);
        return !n && !s ? null : g.createElement(re.div, {ref: Wr(o, t), ...r, "cmdk-separator": "", role: "separator"})
    }), y6 = g.forwardRef((e, t) => {
        let {onValueChange: n, ...r} = e, o = e.value != null, s = Om(), i = qr(u => u.search),
            a = qr(u => u.selectedItemId), l = Wa();
        return g.useEffect(() => {
            e.value != null && s.setState("search", e.value)
        }, [e.value]), g.createElement(re.input, {
            ref: t, ...r,
            "cmdk-input": "",
            autoComplete: "off",
            autoCorrect: "off",
            spellCheck: !1,
            "aria-autocomplete": "list",
            role: "combobox",
            "aria-expanded": !0,
            "aria-controls": l.listId,
            "aria-labelledby": l.labelId,
            "aria-activedescendant": a,
            id: l.inputId,
            type: "text",
            value: o ? e.value : i,
            onChange: u => {
                o || s.setState("search", u.target.value), n == null || n(u.target.value)
            }
        })
    }), x6 = g.forwardRef((e, t) => {
        let {children: n, label: r = "Suggestions", ...o} = e, s = g.useRef(null), i = g.useRef(null),
            a = qr(u => u.selectedItemId), l = Wa();
        return g.useEffect(() => {
            if (i.current && s.current) {
                let u = i.current, d = s.current, f, p = new ResizeObserver(() => {
                    f = requestAnimationFrame(() => {
                        let h = u.offsetHeight;
                        d.style.setProperty("--cmdk-list-height", h.toFixed(1) + "px")
                    })
                });
                return p.observe(u), () => {
                    cancelAnimationFrame(f), p.unobserve(u)
                }
            }
        }, []), g.createElement(re.div, {
            ref: Wr(s, t), ...o,
            "cmdk-list": "",
            role: "listbox",
            tabIndex: -1,
            "aria-activedescendant": a,
            "aria-label": r,
            id: l.listId
        }, Ju(e, u => g.createElement("div", {ref: Wr(i, l.listInnerRef), "cmdk-list-sizer": ""}, u)))
    }), w6 = g.forwardRef((e, t) => {
        let {open: n, onOpenChange: r, overlayClassName: o, contentClassName: s, container: i, ...a} = e;
        return g.createElement(Am, {
            open: n,
            onOpenChange: r
        }, g.createElement(Im, {container: i}, g.createElement(Ha, {
            "cmdk-overlay": "",
            className: o
        }), g.createElement(Ua, {
            "aria-label": e.label,
            "cmdk-dialog": "",
            className: s
        }, g.createElement(mE, {ref: t, ...a}))))
    }), b6 = g.forwardRef((e, t) => qr(n => n.filtered.count === 0) ? g.createElement(re.div, {
        ref: t, ...e,
        "cmdk-empty": "",
        role: "presentation"
    }) : null), C6 = g.forwardRef((e, t) => {
        let {progress: n, children: r, label: o = "Loading...", ...s} = e;
        return g.createElement(re.div, {
            ref: t, ...s,
            "cmdk-loading": "",
            role: "progressbar",
            "aria-valuenow": n,
            "aria-valuemin": 0,
            "aria-valuemax": 100,
            "aria-label": o
        }, Ju(e, i => g.createElement("div", {"aria-hidden": !0}, i)))
    }), bt = Object.assign(mE, {
        List: x6,
        Item: m6,
        Input: y6,
        Group: g6,
        Separator: v6,
        Dialog: w6,
        Empty: b6,
        Loading: C6
    });

function S6(e, t) {
    let n = e.nextElementSibling;
    for (; n;) {
        if (n.matches(t)) return n;
        n = n.nextElementSibling
    }
}

function N6(e, t) {
    let n = e.previousElementSibling;
    for (; n;) {
        if (n.matches(t)) return n;
        n = n.previousElementSibling
    }
}

function gE(e) {
    let t = g.useRef(e);
    return Do(() => {
        t.current = e
    }), t
}

var Do = typeof window > "u" ? g.useEffect : g.useLayoutEffect;

function qo(e) {
    let t = g.useRef();
    return t.current === void 0 && (t.current = e()), t
}

function qr(e) {
    let t = Om(), n = () => e(t.snapshot());
    return g.useSyncExternalStore(t.subscribe, n, n)
}

function vE(e, t, n, r = []) {
    let o = g.useRef(), s = Wa();
    return Do(() => {
        var i;
        let a = (() => {
            var u;
            for (let d of n) {
                if (typeof d == "string") return d.trim();
                if (typeof d == "object" && "current" in d) return d.current ? (u = d.current.textContent) == null ? void 0 : u.trim() : o.current
            }
        })(), l = r.map(u => u.trim());
        s.value(e, a, l), (i = t.current) == null || i.setAttribute(Xo, a), o.current = a
    }), o
}

var E6 = () => {
    let [e, t] = g.useState(), n = qo(() => new Map);
    return Do(() => {
        n.current.forEach(r => r()), n.current = new Map
    }, [e]), (r, o) => {
        n.current.set(r, o), t({})
    }
};

function k6(e) {
    let t = e.type;
    return typeof t == "function" ? t(e.props) : "render" in t ? t.render(e.props) : e
}

function Ju({asChild: e, children: t}, n) {
    return e && g.isValidElement(t) ? g.cloneElement(k6(t), {ref: t.ref}, n(t.props.children)) : n(t)
}

var _6 = {
    position: "absolute",
    width: "1px",
    height: "1px",
    padding: "0",
    margin: "-1px",
    overflow: "hidden",
    clip: "rect(0, 0, 0, 0)",
    whiteSpace: "nowrap",
    borderWidth: "0"
};
const j6 = Am, P6 = Im, yE = g.forwardRef(({className: e, ...t}, n) => c.jsx(Ha, {
    ref: n,
    className: se("fixed inset-0 z-50 bg-black/50 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0", e), ...t
}));
yE.displayName = Ha.displayName;
const xE = g.forwardRef(({
                             className: e,
                             children: t,
                             ...n
                         }, r) => c.jsxs(P6, {
    children: [c.jsx(yE, {}), c.jsxs(Ua, {
        ref: r,
        className: se("fixed left-[50%] top-[50%] z-50 grid w-full max-w-lg translate-x-[-50%] translate-y-[-50%] gap-4 border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] sm:rounded-lg", e), ...n,
        children: [t, c.jsxs(uE, {
            className: "absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground",
            children: [c.jsx(Zs, {className: "h-4 w-4"}), c.jsx("span", {className: "sr-only", children: "Close"})]
        })]
    })]
}));
xE.displayName = Ua.displayName;
const T6 = g.forwardRef(({className: e, ...t}, n) => c.jsx(Qu, {
    ref: n,
    className: se("text-lg font-semibold leading-none tracking-tight", e), ...t
}));
T6.displayName = Qu.displayName;
const M6 = g.forwardRef(({className: e, ...t}, n) => c.jsx(Zu, {
    ref: n,
    className: se("text-sm text-muted-foreground", e), ...t
}));
M6.displayName = Zu.displayName;
const ed = g.forwardRef(({className: e, ...t}, n) => c.jsx(bt, {
    ref: n,
    className: se("flex h-full w-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground", e), ...t
}));
ed.displayName = bt.displayName;
const wE = ({children: e, ...t}) => c.jsx(j6, {
    ...t,
    children: c.jsx(xE, {
        className: "overflow-hidden p-0 shadow-lg",
        children: c.jsx(ed, {
            className: "[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground [&_[cmdk-group]:not([hidden])_~[cmdk-group]]:pt-0 [&_[cmdk-group]]:px-2 [&_[cmdk-input-wrapper]_svg]:h-5 [&_[cmdk-input-wrapper]_svg]:w-5 [&_[cmdk-input]]:h-12 [&_[cmdk-item]]:px-2 [&_[cmdk-item]]:py-3 [&_[cmdk-item]_svg]:h-5 [&_[cmdk-item]_svg]:w-5",
            children: e
        })
    })
}), Dm = g.forwardRef(({className: e, ...t}, n) => c.jsxs("div", {
    className: "flex items-center border-b px-3",
    "cmdk-input-wrapper": "",
    children: [c.jsx(Jj, {className: "mr-2 h-4 w-4 shrink-0 opacity-50"}), c.jsx(bt.Input, {
        ref: n,
        className: se("flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50", e), ...t
    })]
}));
Dm.displayName = bt.Input.displayName;
const $m = g.forwardRef(({className: e, ...t}, n) => c.jsx(bt.List, {
    ref: n,
    className: se("max-h-[300px] overflow-y-auto overflow-x-hidden", e), ...t
}));
$m.displayName = bt.List.displayName;
const Lm = g.forwardRef((e, t) => c.jsx(bt.Empty, {ref: t, className: "py-6 text-center text-sm", ...e}));
Lm.displayName = bt.Empty.displayName;
const Fm = g.forwardRef(({className: e, ...t}, n) => c.jsx(bt.Group, {
    ref: n,
    className: se("overflow-hidden p-1 text-foreground [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground", e), ...t
}));
Fm.displayName = bt.Group.displayName;
const R6 = g.forwardRef(({className: e, ...t}, n) => c.jsx(bt.Separator, {
    ref: n,
    className: se("-mx-1 h-px bg-border", e), ...t
}));
R6.displayName = bt.Separator.displayName;
const zm = g.forwardRef(({className: e, ...t}, n) => c.jsx(bt.Item, {
    ref: n,
    className: se("relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none data-[disabled=true]:pointer-events-none data-[selected='true']:bg-accent data-[selected=true]:text-accent-foreground data-[disabled=true]:opacity-50", e), ...t
}));
zm.displayName = bt.Item.displayName;
console.log("================================================");
console.log(window.data.variables);
const Xc = window.data.variables;
console.log("================================================");

function oc({value: e, onChange: t, placeholder: n, id: r, className: o}) {
    const [s, i] = g.useState(!1), a = u => {
        const d = e + `{{${u}}}`;
        t(d), i(!1)
    }, l = Xc.reduce((u, d) => {
        const f = d.category || "Other";
        return u[f] || (u[f] = []), u[f].push(d), u
    }, {});
    return c.jsxs("div", {
        className: `flex gap-2 items-center w-full ${o || ""}`,
        children: [c.jsx(De, {
            id: r,
            value: e,
            onChange: u => t(u.target.value),
            placeholder: n,
            className: "w-full"
        }), c.jsx(le, {
            variant: "outline",
            size: "icon",
            onClick: () => i(!0),
            type: "button",
            children: c.jsx(gu, {className: "h-4 w-4"})
        }), c.jsx(wE, {
            open: s,
            onOpenChange: i,
            children: c.jsxs(ed, {
                children: [c.jsx(Dm, {placeholder: "Search variables..."}), c.jsxs($m, {
                    children: [c.jsx(Lm, {children: "No variables found."}), Object.entries(l).map(([u, d]) => c.jsx(Fm, {
                        heading: u,
                        children: d.map(f => c.jsx(zm, {onSelect: () => a(f.value), children: f.label}, f.value))
                    }, u))]
                })]
            })
        })]
    })
}

const A6 = ({data: e, id: t}) => {
    var L, $, B, k;
    const {deleteNode: n} = rt(), [r, o] = g.useState(((L = e.settings) == null ? void 0 : L.selectedTemplateId) || ""), [s, i] = g.useState((($ = e.settings) == null ? void 0 : $.parameters) || {}), [a, l] = g.useState(((B = e.settings) == null ? void 0 : B.fileUrl) || ""), [u, d] = g.useState(((k = e.settings) == null ? void 0 : k.videoUrl) || ""), [f, p] = g.useState(!1), [h, x] = g.useState(null);
    g.useEffect(() => {
        e && (e.settings = {selectedTemplateId: r, parameters: s, fileUrl: a, videoUrl: u})
    }, [r, s, a, u, e]);
    const m = g.useMemo(() => {
            console.log("Selected template ID:", r);
            const M = X0.find(j => j.id === r);
            return console.log("Found template:", M), M
        }, [r]), b = (M, j) => {
            const A = {...s, [M]: j};
            i(A), e && (e.settings = {...e.settings, parameters: A})
        }, y = (M, j = "BODY") => {
            if (!M) return [];
            const A = M.match(/{{(\d+)}}/g) || [];
            return [...new Set(A.map(_ => {
                const R = _.replace(/[{}]/g, "");
                return `${j}_${R}`
            }))]
        }, w = (M, j = "BODY") => M ? M.replace(/{{(\d+)}}/g, (A, P) => {
            const _ = `${j}_${P}`;
            return s[_] || A
        }) : "", v = g.useCallback((M, j) => {
            var _;
            const A = (_ = M.target.files) == null ? void 0 : _[0];
            if (!A) return;
            if (j === "image" && !A.type.startsWith("image/")) {
                x("Please upload an image file");
                return
            }
            if (j === "video" && !A.type.startsWith("video/")) {
                x("Please upload a video file");
                return
            }
            if (j === "document" && A.type !== "application/pdf") {
                x("Please upload a PDF file");
                return
            }
            p(!0), x(null);
            const P = new FormData;
            P.append("file", A), P.append("type", j), fetch("/flowmakermedia", {
                method: "POST",
                body: P
            }).then(R => R.json()).then(R => {
                if (R.error) throw new Error(R.error);
                j === "image" ? (l(R.url), e && (e.settings = {
                    ...e.settings,
                    fileUrl: R.url
                })) : j === "video" ? (d(R.url), e && (e.settings = {
                    ...e.settings,
                    videoUrl: R.url
                })) : j === "document" && (l(R.url), e && (e.settings = {...e.settings, fileUrl: R.url})), p(!1)
            }).catch(R => {
                console.error("Error uploading file:", R), x(R.message || "Failed to upload file"), p(!1)
            })
        }, [e]), C = M => {
            var j, A, P, _;
            if (!M.format) return null;
            switch (M.format) {
                case"TEXT":
                    return c.jsx("div", {className: "font-medium mb-2 text-gray-700", children: w(M.text, "HEADER")});
                case"IMAGE":
                    return (A = (j = M.example) == null ? void 0 : j.header_handle) != null && A[0] ? c.jsxs("div", {
                        className: "mb-2",
                        children: [a ? c.jsxs("div", {
                            className: "relative group",
                            children: [c.jsx("img", {
                                src: a,
                                alt: "Header",
                                className: "w-full h-32 object-cover rounded-md"
                            }), c.jsx("div", {
                                className: "absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center",
                                children: c.jsx(le, {
                                    variant: "secondary", size: "sm", onClick: () => {
                                        const R = document.getElementById(`image-upload-${t}`);
                                        R == null || R.click()
                                    }, children: "Change Image"
                                })
                            })]
                        }) : c.jsxs("div", {
                            className: "border-2 border-dashed border-gray-200 rounded-md p-4 text-center cursor-pointer hover:border-gray-300 transition-colors",
                            onClick: () => {
                                const R = document.getElementById(`image-upload-${t}`);
                                R == null || R.click()
                            },
                            children: [c.jsx(Co, {className: "w-6 h-6 mx-auto mb-2 text-gray-400"}), c.jsx("div", {
                                className: "text-sm text-gray-500",
                                children: "Click to upload an image"
                            })]
                        }), c.jsx("input", {
                            type: "file",
                            id: `image-upload-${t}`,
                            className: "hidden",
                            accept: "image/*",
                            onChange: R => v(R, "image"),
                            disabled: f
                        }), f && c.jsx("div", {
                            className: "text-sm text-center text-blue-600",
                            children: "Uploading image..."
                        }), h && c.jsx("div", {className: "text-sm text-center text-red-600", children: h})]
                    }) : null;
                case"VIDEO":
                    return (_ = (P = M.example) == null ? void 0 : P.header_handle) != null && _[0] ? c.jsxs("div", {
                        className: "mb-2",
                        children: [u ? c.jsxs("div", {
                            className: "relative group",
                            children: [c.jsx("video", {
                                src: u,
                                className: "w-full h-32 object-cover rounded-md",
                                controls: !0
                            }), c.jsx("div", {
                                className: "absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center",
                                children: c.jsx(le, {
                                    variant: "secondary", size: "sm", onClick: () => {
                                        const R = document.getElementById(`video-upload-${t}`);
                                        R == null || R.click()
                                    }, children: "Change Video"
                                })
                            })]
                        }) : c.jsxs("div", {
                            className: "border-2 border-dashed border-gray-200 rounded-md p-4 text-center cursor-pointer hover:border-gray-300 transition-colors",
                            onClick: () => {
                                const R = document.getElementById(`video-upload-${t}`);
                                R == null || R.click()
                            },
                            children: [c.jsx(Co, {className: "w-6 h-6 mx-auto mb-2 text-gray-400"}), c.jsx("div", {
                                className: "text-sm text-gray-500",
                                children: "Click to upload a video"
                            })]
                        }), c.jsx("input", {
                            type: "file",
                            id: `video-upload-${t}`,
                            className: "hidden",
                            accept: "video/*",
                            onChange: R => v(R, "video"),
                            disabled: f
                        }), f && c.jsx("div", {
                            className: "text-sm text-center text-blue-600",
                            children: "Uploading video..."
                        }), h && c.jsx("div", {className: "text-sm text-center text-red-600", children: h})]
                    }) : null;
                case"DOCUMENT":
                    return c.jsxs("div", {
                        className: "mb-2",
                        children: [c.jsxs("div", {
                            className: "bg-gray-100 p-3 rounded-md flex items-center gap-2",
                            children: [c.jsx(bo, {className: "h-5 w-5 text-indigo-600"}), c.jsxs("div", {
                                children: [c.jsx("div", {
                                    className: "font-medium",
                                    children: "Document"
                                }), c.jsx("div", {
                                    className: "text-xs text-gray-500",
                                    children: a ? "PDF Document Uploaded" : "No document selected"
                                })]
                            })]
                        }), c.jsxs("div", {
                            className: "mt-2 border-2 border-dashed border-gray-200 rounded-md p-4 text-center cursor-pointer hover:border-gray-300 transition-colors",
                            onClick: () => {
                                const R = document.getElementById(`pdf-upload-${t}`);
                                R == null || R.click()
                            },
                            children: [c.jsx(Co, {className: "w-6 h-6 mx-auto mb-2 text-gray-400"}), c.jsx("div", {
                                className: "text-sm text-gray-500",
                                children: "Click to upload a PDF file"
                            })]
                        }), c.jsx("input", {
                            type: "file",
                            id: `pdf-upload-${t}`,
                            className: "hidden",
                            accept: "application/pdf",
                            onChange: R => v(R, "document"),
                            disabled: f
                        }), f && c.jsx("div", {
                            className: "text-sm text-center text-blue-600",
                            children: "Uploading document..."
                        }), h && c.jsx("div", {className: "text-sm text-center text-red-600", children: h})]
                    });
                default:
                    return null
            }
        }, S = M => c.jsx("div", {
            className: "text-sm text-gray-600 mb-2 whitespace-pre-line",
            children: w(M.text, "BODY")
        }), N = M => c.jsx("div", {className: "text-xs text-gray-500 mt-1", children: w(M.text)}), E = M => {
            console.log("Rendering buttons for component:", M);
            const j = M.buttons;
            if (!(j != null && j.length)) return console.log("No buttons found in component:", M), null;
            console.log(`Found ${j.length} buttons to render:`, j);
            const A = j.some(P => P.type === "QUICK_REPLY");
            return c.jsxs("div", {
                className: "space-y-2 mt-3", children: [j.map((P, _) => {
                    console.log(`Rendering button ${_}:`, P);
                    const R = w(P.text);
                    switch (P.type) {
                        case"QUICK_REPLY":
                            return c.jsxs("div", {
                                className: "relative flex items-center",
                                children: [c.jsxs("button", {
                                    className: "w-full text-sm bg-gray-100 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-200 transition-colors flex items-center gap-2",
                                    children: [c.jsx(zh, {className: "h-4 w-4"}), R]
                                }), c.jsx(de, {
                                    type: "source",
                                    position: q.Right,
                                    id: `quick-reply-${_}`,
                                    className: "!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white",
                                    style: {right: "-20px", top: "50%", transform: "translateY(-50%)", zIndex: 50}
                                })]
                            }, _);
                        case"PHONE_NUMBER":
                            return c.jsx("div", {
                                className: "relative flex items-center",
                                children: c.jsxs("button", {
                                    className: "w-full text-sm bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 transition-colors flex items-center gap-2",
                                    title: P.phone_number,
                                    children: [c.jsx(Qj, {className: "h-4 w-4"}), R]
                                })
                            }, _);
                        case"URL":
                            return c.jsx("div", {
                                className: "relative flex items-center",
                                children: c.jsxs("button", {
                                    className: "w-full text-sm bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 transition-colors flex items-center gap-2",
                                    title: P.url,
                                    children: [c.jsx(bo, {className: "h-4 w-4"}), R]
                                })
                            }, _);
                        case"COPY_CODE":
                            return c.jsx("div", {
                                className: "relative flex items-center",
                                children: c.jsxs("button", {
                                    className: "w-full text-sm bg-purple-500 text-white py-2 px-4 rounded-md hover:bg-purple-600 transition-colors flex items-center gap-2",
                                    children: [c.jsx(bo, {className: "h-4 w-4"}), R]
                                })
                            }, _);
                        default:
                            return console.log(`Unknown button type: ${P.type}`), null
                    }
                }), !A && c.jsxs("div", {
                    className: "mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2",
                    children: [c.jsx("span", {
                        className: "text-xs text-gray-500",
                        children: "Always exit"
                    }), c.jsx(de, {
                        type: "source",
                        position: q.Right,
                        id: "always",
                        className: "!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white",
                        style: {position: "relative", right: "-8px", transform: "translateY(0)", display: "inline-block"}
                    })]
                })]
            })
        }, T = g.useMemo(() => {
            if (!m) return [];
            const M = [];
            return m.components.forEach(j => {
                j.text && y(j.text, j.type).forEach(P => {
                    M.includes(P) || M.push(P)
                })
            }), M.sort()
        }, [m]), O = g.useMemo(() => {
            if (!m) return console.log("No template selected, hasButtons = false"), !1;
            console.log("Checking for buttons in template components:", m.components);
            const M = m.components.some(j => {
                if (j.type === "BUTTONS") {
                    if (console.log("Found BUTTONS component:", j), j.buttons && Array.isArray(j.buttons)) {
                        const A = j.buttons.some(P => P.type === "QUICK_REPLY");
                        return console.log("Has quick reply buttons:", A), A
                    }
                    return console.log("No buttons array found in BUTTONS component or it's not an array"), !1
                }
                return !1
            });
            return console.log("hasButtons final result:", M), M
        }, [m]),
        D = g.useMemo(() => m ? m.components.some(M => M.type === "BUTTONS" && M.buttons && M.buttons.length > 0) : !1, [m]);
    return c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: "bg-white rounded-lg shadow-lg w-[300px]",
                children: [c.jsx(de, {
                    type: "target",
                    position: q.Left,
                    style: {left: "-4px", background: "#555", zIndex: 50}
                }), c.jsxs("div", {
                    className: "flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50",
                    children: [c.jsx(ja, {className: "h-4 w-4 text-indigo-600"}), c.jsx("div", {
                        className: "font-medium",
                        children: "Template Message"
                    })]
                }), c.jsx("div", {
                    className: "p-4", children: c.jsxs("div", {
                        className: "space-y-4",
                        children: [c.jsxs("div", {
                            className: "space-y-2",
                            children: [c.jsx(Se, {
                                htmlFor: "template-select",
                                children: "WhatsApp Template"
                            }), c.jsxs(un, {
                                value: r,
                                onValueChange: M => {
                                    o(M), e && (e.settings = {...e.settings, selectedTemplateId: M})
                                },
                                children: [c.jsx(Ut, {
                                    className: "w-full",
                                    children: c.jsx(dn, {placeholder: "Select a template"})
                                }), c.jsx(Wt, {
                                    children: X0.map(M => c.jsx(je, {
                                        value: M.id,
                                        children: M.name
                                    }, M.id))
                                })]
                            })]
                        }), m && c.jsxs(c.Fragment, {
                            children: [T.length > 0 && c.jsx("div", {
                                className: "space-y-2 px-4 pb-4",
                                children: c.jsx("div", {
                                    className: "space-y-2",
                                    children: T.map(M => c.jsx("div", {
                                        className: "w-full",
                                        children: c.jsx(oc, {
                                            id: `var-${M}`,
                                            value: s[M] || "",
                                            onChange: j => b(M, j),
                                            placeholder: `Value for {{${M}}}`,
                                            className: "w-full"
                                        })
                                    }, M))
                                })
                            }), c.jsxs("div", {
                                className: "bg-[#DCF8C6] rounded-lg p-3 space-y-3",
                                children: [m.components.map((M, j) => c.jsxs("div", {children: [M.type === "HEADER" && C(M), M.type === "BODY" && S(M), M.type === "FOOTER" && N(M), M.type === "BUTTONS" && E(M)]}, j)), !O && !D && c.jsxs("div", {
                                    className: "mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2",
                                    children: [c.jsx("span", {
                                        className: "text-xs text-gray-500",
                                        children: "Always exit"
                                    }), c.jsx(de, {
                                        type: "source",
                                        position: q.Right,
                                        id: "always",
                                        className: "!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white",
                                        style: {
                                            position: "relative",
                                            right: "-8px",
                                            transform: "translateY(0)",
                                            display: "inline-block"
                                        }
                                    })]
                                }), O && c.jsxs("div", {
                                    className: "mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2",
                                    children: [c.jsxs("div", {
                                        className: "flex flex-col items-end",
                                        children: [c.jsx("span", {
                                            className: "text-xs text-gray-500",
                                            children: "Else exit"
                                        }), c.jsx("span", {
                                            className: "text-[10px] text-gray-400",
                                            children: "Triggered when user reply is not known option"
                                        })]
                                    }), c.jsx(de, {
                                        type: "source",
                                        position: q.Right,
                                        id: "else",
                                        className: "!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white",
                                        style: {
                                            position: "relative",
                                            right: "-8px",
                                            transform: "translateY(0)",
                                            display: "inline-block"
                                        }
                                    })]
                                })]
                            })]
                        })]
                    })
                })]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => n(t),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, I6 = ({data: e}) => {
    var l, u;
    const {toast: t} = Oh(), {setNodes: n} = $t(),
        r = ((u = (l = e.settings) == null ? void 0 : l.webhook) == null ? void 0 : u.variables) || [],
        o = `${window.location.origin}/api/webhook/${Math.random().toString(36).substring(7)}`, s = () => {
            navigator.clipboard.writeText(o), t({
                title: "URL Copied",
                description: "Webhook URL has been copied to clipboard"
            })
        }, i = () => {
            const d = {id: Math.random().toString(36).substring(7), name: "", type: "string", required: !0};
            n(f => f.map(p => {
                var h, x, m;
                if (p.type === "webhook") {
                    const b = p.data;
                    return {
                        ...p,
                        data: {
                            ...b,
                            settings: {
                                ...b.settings,
                                webhook: {
                                    ...(h = b.settings) == null ? void 0 : h.webhook,
                                    variables: [...((m = (x = b.settings) == null ? void 0 : x.webhook) == null ? void 0 : m.variables) || [], d]
                                }
                            }
                        }
                    }
                }
                return p
            }))
        }, a = (d, f) => {
            n(p => p.map(h => {
                var x;
                if (h.type === "webhook") {
                    const m = h.data, b = r.map(y => y.id === d ? {...y, ...f} : y);
                    return {
                        ...h,
                        data: {
                            ...m,
                            settings: {
                                ...m.settings,
                                webhook: {...(x = m.settings) == null ? void 0 : x.webhook, variables: b}
                            }
                        }
                    }
                }
                return h
            }))
        };
    return c.jsxs("div", {
        className: "bg-white rounded-lg shadow-lg",
        children: [c.jsx(de, {
            type: "source",
            position: q.Right,
            className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
        }), c.jsxs("div", {
            className: "flex items-center gap-2 border-b border-gray-100 px-3 py-2 bg-gray-50",
            children: [c.jsx(i1, {className: "h-4 w-4 text-blue-600 flex-shrink-0"}), c.jsx("div", {
                className: "font-medium leading-none",
                children: "Webhook"
            })]
        }), c.jsxs("div", {
            className: "p-3",
            children: [c.jsxs("div", {
                children: [c.jsx(Se, {
                    className: "block",
                    children: "Webhook URL"
                }), c.jsxs("div", {
                    className: "flex items-center gap-2",
                    children: [c.jsx(De, {value: o, readOnly: !0, className: "text-sm"}), c.jsx(le, {
                        variant: "outline",
                        size: "icon",
                        onClick: s,
                        children: c.jsx(Kj, {className: "h-4 w-4"})
                    })]
                })]
            }), c.jsxs("div", {
                children: [c.jsxs("div", {
                    className: "flex items-center justify-between",
                    children: [c.jsx(Se, {children: "Variables"}), c.jsx(le, {
                        variant: "outline",
                        size: "sm",
                        onClick: i,
                        children: "Add Variable"
                    })]
                }), c.jsx("div", {
                    children: r.map(d => c.jsx("div", {
                        className: "flex items-start gap-2 pt-2",
                        children: c.jsx(De, {
                            placeholder: "Variable name",
                            value: d.name,
                            onChange: f => a(d.id, {name: f.target.value}),
                            className: "flex-1"
                        })
                    }, d.id))
                })]
            })]
        })]
    })
}, O6 = [{value: "equals", label: "Equals"}, {value: "not_equals", label: "Not Equals"}, {
    value: "greater_than",
    label: "Greater Than"
}, {value: "less_than", label: "Less Than"}, {value: "contains", label: "Contains"}, {
    value: "not_contains",
    label: "Not Contains"
}], D6 = ({data: e}) => {
    var u, d, f, p;
    const {
            getNodes: t,
            setNodes: n
        } = $t(), [r, o] = g.useState(((u = e.settings) == null ? void 0 : u.conditions) || []),
        s = t().find(h => h.type === "webhook");
    (p = (f = (d = s == null ? void 0 : s.data) == null ? void 0 : d.settings) == null ? void 0 : f.webhook) != null && p.variables;
    const i = g.useCallback(h => {
        n(x => x.map(m => m.data.id === e.id ? {
            ...m,
            data: {...m.data, settings: {...m.data.settings, conditions: h}}
        } : m))
    }, [e.id, n]), a = () => {
        const h = {id: Math.random().toString(36).substring(7), variableId: "", operator: "equals", value: ""},
            x = [...r, h];
        o(x), i(x)
    }, l = h => {
        const x = r.filter(m => m.id !== h);
        o(x), i(x)
    };
    return c.jsxs("div", {
        className: "bg-white rounded-lg shadow-lg",
        children: [c.jsx(de, {
            type: "target",
            position: q.Left,
            className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
        }), c.jsxs("div", {
            className: "flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50",
            children: [c.jsx(t1, {className: "h-4 w-4 text-purple-600"}), c.jsx("div", {
                className: "font-medium",
                children: "Branch"
            })]
        }), c.jsx("div", {
            className: "p-4", children: c.jsxs("div", {
                className: "space-y-4",
                children: [c.jsxs("div", {
                    className: "flex items-center justify-between",
                    children: [c.jsx("h3", {
                        className: "text-sm font-medium",
                        children: "Branch"
                    }), c.jsxs(le, {
                        variant: "outline",
                        size: "sm",
                        onClick: a,
                        children: [c.jsx(Pa, {className: "h-4 w-4 mr-1"}), "Add Condition"]
                    })]
                }), c.jsx("div", {
                    className: "space-y-3", children: r.map(h => c.jsxs("div", {
                        className: "flex items-center gap-2",
                        children: [c.jsxs(un, {
                            value: h.variableId,
                            onValueChange: x => {
                                const m = r.map(b => b.id === h.id ? {...b, variableId: x} : b);
                                o(m), i(m)
                            },
                            children: [c.jsx(Ut, {
                                className: "w-[180px]",
                                children: c.jsx(dn, {placeholder: "Variable"})
                            }), c.jsx(Wt, {
                                children: Object.entries(Xc.reduce((x, m) => {
                                    const b = m.category || "Other";
                                    return x[b] || (x[b] = []), x[b].push(m), x
                                }, {})).map(([x, m]) => c.jsx("div", {})).concat(Xc.map(x => c.jsx(je, {
                                    value: x.value,
                                    children: x.label
                                }, x.value)))
                            })]
                        }), c.jsxs(un, {
                            value: h.operator,
                            onValueChange: x => {
                                const m = r.map(b => b.id === h.id ? {...b, operator: x} : b);
                                o(m), i(m)
                            },
                            children: [c.jsx(Ut, {
                                className: "w-[120px]",
                                children: c.jsx(dn, {placeholder: "Operator"})
                            }), c.jsx(Wt, {
                                children: O6.map(x => c.jsx(je, {
                                    value: x.value,
                                    children: x.label
                                }, x.value))
                            })]
                        }), c.jsx(De, {
                            placeholder: "Value", value: h.value, onChange: x => {
                                const m = r.map(b => b.id === h.id ? {...b, value: x.target.value} : b);
                                o(m), i(m)
                            }, className: "flex-1"
                        }), c.jsx(le, {
                            variant: "ghost",
                            size: "icon",
                            onClick: () => l(h.id),
                            children: c.jsx(Zs, {className: "h-4 w-4"})
                        }), c.jsxs("div", {
                            className: "flex flex-col gap-4 ml-2",
                            children: [c.jsx(de, {
                                type: "source",
                                position: q.Right,
                                id: `condition-${h.id}-true`,
                                className: "!bg-green-500 !w-3 !h-3 !rounded-full",
                                style: {top: "25%"}
                            }), c.jsx(de, {
                                type: "source",
                                position: q.Right,
                                id: `condition-${h.id}-false`,
                                className: "!bg-red-500 !w-3 !h-3 !rounded-full",
                                style: {top: "75%"}
                            })]
                        })]
                    }, h.id))
                })]
            })
        })]
    })
}, $6 = ({id: e, data: t}) => {
    const [n, r] = g.useState(!1), [o, s] = g.useState(t.label), [i, a] = g.useState(t.settings || {
        waitTime: 0,
        waitUnit: "seconds"
    }), {deleteNode: l, updateNode: u} = rt(), d = p => {
        const h = p.target.value;
        s(h), u(e, {label: h})
    }, f = (p, h) => {
        const x = {...i, [p]: h};
        a(x), u(e, {settings: x})
    };
    return g.useEffect(() => {
        var p, h;
        (p = t.settings) != null && p.waitUnit || f("waitUnit", "seconds"), ((h = t.settings) == null ? void 0 : h.waitTime) === void 0 && f("waitTime", 0)
    }, []), c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: "bg-white rounded-lg shadow-md p-3 min-w-[200px]",
                children: [c.jsx(de, {
                    type: "target",
                    position: q.Left,
                    className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
                }), c.jsx(de, {
                    type: "source",
                    position: q.Right,
                    className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
                }), c.jsxs("div", {
                    className: "space-y-2",
                    children: [c.jsxs("div", {
                        className: "flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50 -mx-3 -mt-3",
                        children: [c.jsx(Bh, {className: "h-4 w-4 text-orange-600"}), c.jsx("div", {
                            className: "font-medium",
                            children: n ? c.jsx(De, {
                                type: "text",
                                value: o,
                                onChange: d,
                                onBlur: () => r(!1),
                                onKeyPress: p => {
                                    p.key === "Enter" && r(!1)
                                },
                                autoFocus: !0
                            }) : c.jsx("div", {className: "cursor-pointer", onClick: () => r(!0), children: o})
                        })]
                    }), c.jsx("div", {
                        className: "pt-2 space-y-2",
                        children: c.jsxs("div", {
                            className: "flex gap-2",
                            children: [c.jsx(De, {
                                type: "number",
                                placeholder: "Duration",
                                value: i.waitTime,
                                onChange: p => f("waitTime", parseInt(p.target.value) || 0),
                                className: "flex-1",
                                min: 0
                            }), c.jsxs(un, {
                                value: i.waitUnit,
                                onValueChange: p => f("waitUnit", p),
                                children: [c.jsx(Ut, {
                                    className: "w-[110px]",
                                    children: c.jsx(dn, {placeholder: "Unit"})
                                }), c.jsxs(Wt, {
                                    children: [c.jsx(je, {
                                        value: "seconds",
                                        children: "Seconds"
                                    }), c.jsx(je, {value: "minutes", children: "Minutes"}), c.jsx(je, {
                                        value: "hours",
                                        children: "Hours"
                                    }), c.jsx(je, {value: "days", children: "Days"})]
                                })]
                            })]
                        })
                    })]
                })]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => l(e),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, L6 = ({
             headers: e,
             onAddHeader: t,
             onUpdateHeader: n,
             onRemoveHeader: r
         }) => c.jsxs("div", {
    children: [c.jsxs("div", {
        className: "flex items-center justify-between mb-2",
        children: [c.jsx(Se, {children: "Headers"}), c.jsxs(le, {
            variant: "outline",
            size: "sm",
            onClick: t,
            children: [c.jsx(Pa, {className: "h-4 w-4 mr-2"}), "Add Header"]
        })]
    }), c.jsx("div", {
        className: "space-y-2",
        children: e.map(o => c.jsxs("div", {
            className: "flex gap-2",
            children: [c.jsx(De, {
                placeholder: "Key",
                value: o.key,
                onChange: s => n(o.id, s.target.value, o.value),
                className: "flex-1"
            }), c.jsx(De, {
                placeholder: "Value",
                value: o.value,
                onChange: s => n(o.id, o.key, s.target.value),
                className: "flex-1"
            }), c.jsx(le, {
                variant: "ghost",
                size: "icon",
                onClick: () => r(o.id),
                children: c.jsx(at, {className: "h-4 w-4 text-red-500"})
            })]
        }, o.id))
    })]
}), F6 = ({
              params: e,
              onAddParam: t,
              onUpdateParam: n,
              onRemoveParam: r
          }) => c.jsxs("div", {
    children: [c.jsxs("div", {
        className: "flex items-center justify-between mb-2",
        children: [c.jsx(Se, {children: "Parameters"}), c.jsxs(le, {
            variant: "outline",
            size: "sm",
            onClick: t,
            children: [c.jsx(Pa, {className: "h-4 w-4 mr-2"}), "Add Parameter"]
        })]
    }), c.jsx("div", {
        className: "space-y-2",
        children: e.map(o => c.jsxs("div", {
            className: "flex gap-2",
            children: [c.jsx(De, {
                placeholder: "Key",
                value: o.key,
                onChange: s => n(o.id, s.target.value, o.value),
                className: "flex-1"
            }), c.jsx(De, {
                placeholder: "Value",
                value: o.value,
                onChange: s => n(o.id, o.key, s.target.value),
                className: "flex-1"
            }), c.jsx(le, {
                variant: "ghost",
                size: "icon",
                onClick: () => r(o.id),
                children: c.jsx(at, {className: "h-4 w-4 text-red-500"})
            })]
        }, o.id))
    })]
}), z6 = {method: "GET", url: "", headers: [], params: []}, B6 = ({id: e, data: t}) => {
    var p;
    const {setNodes: n} = $t(), [r, o] = g.useState({...z6, ...((p = t.settings) == null ? void 0 : p.http) || {}});
    g.useEffect(() => {
        n(h => h.map(x => {
            if (x.id === e) {
                const m = x.data || {}, b = m.settings || {};
                return {...x, data: {...m, settings: {...b, http: r}}}
            }
            return x
        }))
    }, [r, e, n]);
    const s = h => {
        o(x => ({...x, ...h}))
    }, i = () => {
        const h = [...r.headers, {id: crypto.randomUUID(), key: "", value: ""}];
        s({headers: h})
    }, a = (h, x, m) => {
        const b = r.headers.map(y => y.id === h ? {...y, key: x, value: m} : y);
        s({headers: b})
    }, l = h => {
        const x = r.headers.filter(m => m.id !== h);
        s({headers: x})
    }, u = () => {
        const h = [...r.params, {id: crypto.randomUUID(), key: "", value: ""}];
        s({params: h})
    }, d = (h, x, m) => {
        const b = r.params.map(y => y.id === h ? {...y, key: x, value: m} : y);
        s({params: b})
    }, f = h => {
        const x = r.params.filter(m => m.id !== h);
        s({params: x})
    };
    return c.jsxs("div", {
        className: "bg-white rounded-lg shadow-lg",
        children: [c.jsx(de, {
            type: "target",
            position: q.Left,
            className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
        }), c.jsxs("div", {
            className: "flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50",
            children: [c.jsx(ra, {className: "h-4 w-4 text-blue-600"}), c.jsx("div", {
                className: "font-medium",
                children: "HTTP Request"
            })]
        }), c.jsx("div", {
            className: "p-4",
            children: c.jsxs("div", {
                className: "space-y-4",
                children: [c.jsxs("div", {
                    className: "flex gap-4",
                    children: [c.jsxs("div", {
                        className: "w-32",
                        children: [c.jsx(Se, {children: "Methods"}), c.jsxs(un, {
                            value: r.method,
                            onValueChange: h => s({method: h}),
                            children: [c.jsx(Ut, {children: c.jsx(dn, {placeholder: "Select method"})}), c.jsxs(Wt, {
                                children: [c.jsx(je, {
                                    value: "GET",
                                    children: "GET"
                                }), c.jsx(je, {value: "POST", children: "POST"}), c.jsx(je, {
                                    value: "PUT",
                                    children: "PUT"
                                }), c.jsx(je, {value: "DELETE", children: "DELETE"})]
                            })]
                        })]
                    }), c.jsxs("div", {
                        className: "flex-1",
                        children: [c.jsx(Se, {children: "URL"}), c.jsx(De, {
                            placeholder: "Enter URL",
                            value: r.url,
                            onChange: h => s({url: h.target.value})
                        })]
                    })]
                }), c.jsx(L6, {
                    headers: r.headers,
                    onAddHeader: i,
                    onUpdateHeader: a,
                    onRemoveHeader: l
                }), c.jsx(F6, {params: r.params, onAddParam: u, onUpdateParam: d, onRemoveParam: f})]
            })
        }), c.jsx(de, {type: "source", position: q.Right, className: "!bg-gray-300 !w-3 !h-3 !rounded-full"})]
    })
}, Ka = g.forwardRef(({
                          className: e,
                          ...t
                      }, n) => c.jsx("textarea", {
    className: se("flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50", e),
    ref: n, ...t
}));
Ka.displayName = "Textarea";

function Ya({value: e, onChange: t, placeholder: n}) {
    const [r, o] = g.useState(!1), s = a => {
        var d;
        const l = ((d = document.activeElement) == null ? void 0 : d.selectionStart) || e.length,
            u = e.slice(0, l) + `{{${a}}}` + e.slice(l);
        t(u), o(!1)
    }, i = Xc.reduce((a, l) => {
        const u = l.category || "Other";
        return a[u] || (a[u] = []), a[u].push(l), a
    }, {});
    return c.jsxs("div", {
        className: "space-y-2",
        children: [c.jsx(Ka, {
            value: e,
            onChange: a => t(a.target.value),
            placeholder: n,
            className: "min-h-[100px]"
        }), c.jsx("div", {
            className: "flex justify-end",
            children: c.jsxs(le, {
                variant: "outline",
                size: "sm",
                onClick: () => o(!0),
                className: "gap-2",
                children: [c.jsx(gu, {className: "h-4 w-4"}), "Variables"]
            })
        }), c.jsx(wE, {
            open: r,
            onOpenChange: o,
            children: c.jsxs(ed, {
                children: [c.jsx(Dm, {placeholder: "Search variables..."}), c.jsxs($m, {
                    children: [c.jsx(Lm, {children: "No variables found."}), Object.entries(i).map(([a, l]) => c.jsx(Fm, {
                        heading: a,
                        children: l.map(u => c.jsx(zm, {onSelect: () => s(u.value), children: u.label}, u.value))
                    }, a))]
                })]
            })
        })]
    })
}

const V6 = ({id: e, data: t}) => {
    var o;
    const {setNodes: n} = $t(), r = g.useCallback(s => {
        n(i => i.map(a => {
            if (a.id === e) {
                const l = typeof a.data == "object" && a.data !== null ? a.data : {},
                    u = typeof l.settings == "object" && l.settings !== null ? l.settings : {};
                return {...a, data: {...l, settings: {...u, message: s}}}
            }
            return a
        }))
    }, [e, n]);
    return c.jsx(lr, {
        id: e,
        title: "Send Message",
        icon: c.jsx(Hh, {className: "h-4 w-4 text-blue-600"}),
        headerBackground: "bg-gray-50",
        borderColor: "border-gray-100",
        children: c.jsx("div", {
            className: "space-y-4",
            children: c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {
                    htmlFor: "message",
                    children: "Message"
                }), c.jsx(Ya, {
                    value: ((o = t.settings) == null ? void 0 : o.message) || "",
                    onChange: r,
                    placeholder: "Enter your message..."
                })]
            })
        })
    })
}, H6 = {image: Vh, video: s1, audio: qj, document: bo}, U6 = ({id: e, data: t, isConnectable: n}) => {
    var s, i;
    const r = ((i = (s = t.settings) == null ? void 0 : s.media) == null ? void 0 : i.type) || "image", o = H6[r];
    return c.jsx(lr, {
        id: e,
        title: "Basic Image Generate",
        icon: c.jsx(o, {className: "h-4 w-4 text-blue-600"}),
        headerBackground: "bg-blue-50",
        borderColor: "border-blue-200",
        nodeWidth: "w-[350px]",
        children: c.jsxs("div", {
            className: "space-y-4",
            children: [c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {children: "Prompt"}), c.jsx(Ka, {
                    placeholder: "Enter the prompt...",
                    className: "min-h-[80px] bg-gray-50"
                })]
            }), c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {children: "Choose AI Model"}), c.jsxs("div", {
                    className: "flex gap-2",
                    children: [c.jsx(le, {
                        variant: "outline",
                        className: "bg-gray-900 text-white hover:bg-gray-800 px-3 py-1 h-auto text-xs rounded-full",
                        children: "+ Add models"
                    }), c.jsx(le, {
                        variant: "outline",
                        className: "bg-white text-gray-700 border-gray-300 hover:bg-gray-50 px-3 py-1 h-auto text-xs rounded-full",
                        children: "Flux 11 Pro"
                    })]
                })]
            }), c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {children: "Aspect Ratio"}), c.jsxs(un, {
                    defaultValue: "16:9",
                    children: [c.jsx(Ut, {
                        className: "bg-gray-50",
                        children: c.jsx(dn, {placeholder: "Select aspect ratio"})
                    }), c.jsxs(Wt, {
                        children: [c.jsx(je, {value: "16:9", children: "16:9"}), c.jsx(je, {
                            value: "4:3",
                            children: "4:3"
                        }), c.jsx(je, {value: "1:1", children: "1:1"}), c.jsx(je, {value: "9:16", children: "9:16"})]
                    })]
                })]
            })]
        })
    })
}, bE = g.memo(({id: e, data: t}) => {
    var r, o;
    const {updateNode: n} = rt();
    return c.jsx(lr, {
        id: e,
        title: "Ask Question",
        icon: c.jsx(e1, {className: "h-4 w-4 text-teal-600"}),
        headerBackground: "bg-teal-50",
        borderColor: "border-teal-200",
        children: c.jsxs("div", {
            className: "space-y-4",
            children: [c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {htmlFor: "question", children: "Question"}), c.jsx(Ka, {
                    id: "question",
                    value: ((r = t.settings) == null ? void 0 : r.question) || "",
                    onChange: s => n(e, {settings: {...t.settings, question: s.target.value}}),
                    placeholder: "Enter your question...",
                    className: "min-h-[80px] bg-gray-50"
                })]
            }), c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {
                    htmlFor: "variableName",
                    children: "Variable name"
                }), c.jsx(De, {
                    id: "variableName",
                    value: ((o = t.settings) == null ? void 0 : o.variableName) || "",
                    onChange: s => n(e, {settings: {...t.settings, variableName: s.target.value}}),
                    placeholder: "e.g. user_response",
                    className: "bg-gray-50"
                })]
            })]
        })
    })
});
bE.displayName = "QuestionNode";
const W6 = ({id: e, data: t}) => {
    var p, h, x;
    const {deleteNode: n} = rt(), {setNodes: r} = $t(), [o, s] = g.useState(!1), [i, a] = g.useState(((p = t.settings) == null ? void 0 : p.caption) || ""), [l, u] = g.useState(null),
        d = g.useCallback(m => {
            var w;
            const b = (w = m.target.files) == null ? void 0 : w[0];
            if (!b) return;
            s(!0), u(null);
            const y = new FormData;
            y.append("file", b), y.append("type", "image"), fetch("/flowmakermedia", {
                method: "POST",
                body: y
            }).then(v => v.json()).then(v => {
                if (v.error) throw new Error(v.error);
                r(C => C.map(S => {
                    if (S.id === e) {
                        const N = S.data.settings || {};
                        return {...S, data: {...S.data, settings: {...N, imageUrl: v.url}}}
                    }
                    return S
                })), s(!1)
            }).catch(v => {
                console.error("Error uploading image:", v), u(v.message || "Failed to upload image"), s(!1)
            })
        }, [e, r]), f = g.useCallback(m => {
            a(m), r(b => b.map(y => {
                if (y.id === e) {
                    const w = y.data.settings || {};
                    return {...y, data: {...y.data, settings: {...w, caption: m}}}
                }
                return y
            }))
        }, [e, r]);
    return c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: "w-[300px] bg-white rounded-lg shadow-lg",
                children: [c.jsx(de, {
                    type: "target",
                    position: q.Left,
                    className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
                }), c.jsxs("div", {
                    className: "flex items-center gap-2 border-b border-gray-100 px-3 py-2 bg-gray-50",
                    children: [c.jsx(Vh, {className: "h-4 w-4 text-purple-600 flex-shrink-0"}), c.jsx("div", {
                        className: "font-medium leading-none",
                        children: "Send Image"
                    })]
                }), c.jsxs("div", {
                    className: "p-4 space-y-4", children: [c.jsxs("div", {
                        className: "space-y-2",
                        children: [(h = t.settings) != null && h.imageUrl ? c.jsxs("div", {
                            className: "relative group",
                            children: [c.jsx("img", {
                                src: t.settings.imageUrl,
                                alt: "Uploaded preview",
                                className: "w-full h-[200px] object-cover rounded-md"
                            }), c.jsx("div", {
                                className: "absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center",
                                children: c.jsx(le, {
                                    variant: "secondary", size: "sm", onClick: () => {
                                        const m = document.getElementById(`image-upload-${e}`);
                                        m == null || m.click()
                                    }, children: "Change Image"
                                })
                            })]
                        }) : c.jsxs("div", {
                            className: "border-2 border-dashed border-gray-200 rounded-md p-8 text-center cursor-pointer hover:border-gray-300 transition-colors",
                            onClick: () => {
                                const m = document.getElementById(`image-upload-${e}`);
                                m == null || m.click()
                            },
                            children: [c.jsx(Co, {className: "w-8 h-8 mx-auto mb-2 text-gray-400"}), c.jsx("div", {
                                className: "text-sm text-gray-500",
                                children: "Click to upload an image"
                            })]
                        }), c.jsx("input", {
                            type: "file",
                            id: `image-upload-${e}`,
                            className: "hidden",
                            accept: "image/*",
                            onChange: d,
                            disabled: o
                        }), o && c.jsx("div", {
                            className: "text-sm text-center text-blue-600",
                            children: "Uploading image..."
                        }), l && c.jsx("div", {className: "text-sm text-center text-red-600", children: l})]
                    }), c.jsxs("div", {
                        className: "space-y-2",
                        children: [c.jsx(Se, {
                            htmlFor: "caption",
                            children: "Caption"
                        }), c.jsx(Ya, {
                            value: ((x = t.settings) == null ? void 0 : x.caption) || "",
                            onChange: f,
                            placeholder: "Add a caption..."
                        })]
                    })]
                }), c.jsx(de, {type: "source", position: q.Right, className: "!bg-gray-300 !w-3 !h-3 !rounded-full"})]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => n(e),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, K6 = ({id: e, data: t}) => {
    var x, m, b;
    const {deleteNode: n} = rt(), {setNodes: r} = $t(), [o, s] = g.useState(!1), [i, a] = g.useState(((x = t.settings) == null ? void 0 : x.pdfUrl) || ""), [l, u] = g.useState(((m = t.settings) == null ? void 0 : m.caption) || ""), [d, f] = g.useState(null),
        p = g.useCallback(y => {
            var C;
            const w = (C = y.target.files) == null ? void 0 : C[0];
            if (!w) return;
            s(!0), f(null);
            const v = new FormData;
            v.append("file", w), v.append("type", "pdf"), fetch("/flowmakermedia", {
                method: "POST",
                body: v
            }).then(S => S.json()).then(S => {
                if (S.error) throw new Error(S.error);
                const N = S.url;
                a(N), r(E => E.map(T => {
                    if (T.id === e) {
                        const O = T.data.settings || {};
                        return {...T, data: {...T.data, settings: {...O, pdfUrl: N}}}
                    }
                    return T
                })), s(!1)
            }).catch(S => {
                console.error("Error uploading PDF:", S), f(S.message || "Failed to upload PDF"), s(!1)
            })
        }, [e, r]), h = g.useCallback(y => {
            u(y), r(w => w.map(v => {
                if (v.id === e) {
                    const C = v.data.settings || {};
                    return {...v, data: {...v.data, settings: {...C, caption: y}}}
                }
                return v
            }))
        }, [e, r]);
    return c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: "w-[300px] bg-white rounded-lg shadow-lg",
                children: [c.jsx(de, {
                    type: "target",
                    position: q.Left,
                    className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
                }), c.jsxs("div", {
                    className: "flex items-center gap-2 border-b border-gray-100 px-3 py-2 bg-gray-50",
                    children: [c.jsx(sp, {className: "h-4 w-4 text-red-600 flex-shrink-0"}), c.jsx("div", {
                        className: "font-medium leading-none",
                        children: "Send PDF"
                    })]
                }), c.jsxs("div", {
                    className: "p-4 space-y-4",
                    children: [c.jsxs("div", {
                        className: "border-2 border-dashed border-gray-200 rounded-md p-4 text-center cursor-pointer hover:border-gray-300 transition-colors",
                        onClick: () => {
                            const y = document.getElementById(`pdf-upload-${e}`);
                            y == null || y.click()
                        },
                        children: [c.jsx(Co, {className: "w-6 h-6 mx-auto mb-2 text-gray-400"}), c.jsx("div", {
                            className: "text-sm text-gray-500",
                            children: "Click to upload a PDF file"
                        })]
                    }), c.jsx("input", {
                        type: "file",
                        id: `pdf-upload-${e}`,
                        className: "hidden",
                        accept: "application/pdf",
                        onChange: p,
                        disabled: o
                    }), o && c.jsx("div", {
                        className: "text-sm text-center text-blue-600",
                        children: "Uploading PDF..."
                    }), d && c.jsx("div", {
                        className: "text-sm text-center text-red-600",
                        children: d
                    }), i && c.jsxs("div", {
                        className: "p-3 bg-gray-50 rounded-md border border-gray-200 flex items-center gap-2",
                        children: [c.jsx(sp, {className: "h-5 w-5 text-red-500"}), c.jsx("div", {
                            className: "text-sm truncate flex-1",
                            children: "Uploaded PDF"
                        })]
                    }), c.jsxs("div", {
                        className: "space-y-2",
                        children: [c.jsx(Se, {
                            htmlFor: "caption",
                            children: "Caption"
                        }), c.jsx(Ya, {
                            value: ((b = t.settings) == null ? void 0 : b.caption) || "",
                            onChange: h,
                            placeholder: "Add a caption..."
                        })]
                    })]
                }), c.jsx(de, {type: "source", position: q.Right, className: "!bg-gray-300 !w-3 !h-3 !rounded-full"})]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => n(e),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, Y6 = ({id: e, data: t}) => {
    var p, h, x;
    const {deleteNode: n} = rt(), {setNodes: r} = $t(), [o, s] = g.useState(!1), [i, a] = g.useState(((p = t.settings) == null ? void 0 : p.caption) || ""), [l, u] = g.useState(null),
        d = g.useCallback(m => {
            var w;
            const b = (w = m.target.files) == null ? void 0 : w[0];
            if (!b) return;
            s(!0), u(null);
            const y = new FormData;
            y.append("file", b), y.append("type", "video"), fetch("/flowmakermedia", {
                method: "POST",
                body: y
            }).then(v => v.json()).then(v => {
                if (v.error) throw new Error(v.error);
                r(C => C.map(S => {
                    if (S.id === e) {
                        const N = S.data.settings || {};
                        return {...S, data: {...S.data, settings: {...N, videoUrl: v.url}}}
                    }
                    return S
                })), s(!1)
            }).catch(v => {
                console.error("Error uploading video:", v), u(v.message || "Failed to upload video"), s(!1)
            })
        }, [e, r]), f = g.useCallback(m => {
            a(m), r(b => b.map(y => {
                if (y.id === e) {
                    const w = y.data.settings || {};
                    return {...y, data: {...y.data, settings: {...w, caption: m}}}
                }
                return y
            }))
        }, [e, r]);
    return c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: "w-[300px] bg-white rounded-lg shadow-lg",
                children: [c.jsx(de, {
                    type: "target",
                    position: q.Left,
                    className: "!bg-gray-300 !w-3 !h-3 !rounded-full"
                }), c.jsxs("div", {
                    className: "flex items-center gap-2 border-b border-gray-100 px-3 py-2 bg-gray-50",
                    children: [c.jsx(s1, {className: "h-4 w-4 text-purple-600 flex-shrink-0"}), c.jsx("div", {
                        className: "font-medium leading-none",
                        children: "Send Video"
                    })]
                }), c.jsxs("div", {
                    className: "p-4 space-y-4", children: [c.jsxs("div", {
                        className: "space-y-2",
                        children: [(h = t.settings) != null && h.videoUrl ? c.jsxs("div", {
                            className: "relative group",
                            children: [c.jsx("video", {
                                src: t.settings.videoUrl,
                                controls: !0,
                                className: "w-full h-[200px] object-cover rounded-md"
                            }), c.jsx("div", {
                                className: "absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center",
                                children: c.jsx(le, {
                                    variant: "secondary", size: "sm", onClick: () => {
                                        const m = document.getElementById(`video-upload-${e}`);
                                        m == null || m.click()
                                    }, children: "Change Video"
                                })
                            })]
                        }) : c.jsxs("div", {
                            className: "border-2 border-dashed border-gray-200 rounded-md p-8 text-center cursor-pointer hover:border-gray-300 transition-colors",
                            onClick: () => {
                                const m = document.getElementById(`video-upload-${e}`);
                                m == null || m.click()
                            },
                            children: [c.jsx(Co, {className: "w-8 h-8 mx-auto mb-2 text-gray-400"}), c.jsx("div", {
                                className: "text-sm text-gray-500",
                                children: "Click to upload a video"
                            })]
                        }), c.jsx("input", {
                            type: "file",
                            id: `video-upload-${e}`,
                            className: "hidden",
                            accept: "video/*",
                            onChange: d,
                            disabled: o
                        }), o && c.jsx("div", {
                            className: "text-sm text-center text-blue-600",
                            children: "Uploading video..."
                        }), l && c.jsx("div", {className: "text-sm text-center text-red-600", children: l})]
                    }), c.jsxs("div", {
                        className: "space-y-2",
                        children: [c.jsx(Se, {
                            htmlFor: "caption",
                            children: "Caption"
                        }), c.jsx(Ya, {
                            value: ((x = t.settings) == null ? void 0 : x.caption) || "",
                            onChange: f,
                            placeholder: "Add a caption..."
                        })]
                    })]
                }), c.jsx(de, {type: "source", position: q.Right, className: "!bg-gray-300 !w-3 !h-3 !rounded-full"})]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => n(e),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, G6 = ({data: e, id: t, selected: n, isConnectable: r}) => {
    var d, f, p, h, x, m, b;
    const {deleteNode: o} = rt(), [s, i] = g.useState({
        header: ((d = e.settings) == null ? void 0 : d.header) || "",
        body: ((f = e.settings) == null ? void 0 : f.body) || "",
        footer: ((p = e.settings) == null ? void 0 : p.footer) || "",
        activeButtons: ((h = e.settings) == null ? void 0 : h.activeButtons) || 1,
        button1: ((x = e.settings) == null ? void 0 : x.button1) || "",
        button2: ((m = e.settings) == null ? void 0 : m.button2) || "",
        button3: ((b = e.settings) == null ? void 0 : b.button3) || ""
    });
    g.useEffect(() => {
        e.settings && Object.keys(s).forEach(y => {
            e.settings[y] = s[y]
        })
    }, [s, e]);
    const a = g.useCallback((y, w) => {
        i(v => ({...v, [y]: w}))
    }, []), l = g.useCallback(() => {
        s.activeButtons < 3 && a("activeButtons", s.activeButtons + 1)
    }, [s.activeButtons, a]), u = g.useCallback(y => {
        if (s.activeButtons > 1) {
            const w = {...s};
            for (let v = y; v < 3; v++) w[`button${v}`] = s[`button${v + 1}`] || "";
            w.button3 = "", w.activeButtons = s.activeButtons - 1, i(w)
        }
    }, [s]);
    return c.jsxs(no, {
        children: [c.jsx(ro, {
            children: c.jsxs("div", {
                className: "bg-white rounded-lg shadow-lg w-[300px]",
                children: [c.jsx(de, {
                    type: "target",
                    position: q.Left,
                    style: {left: "-4px", background: "#555", zIndex: 50},
                    isConnectable: r
                }), c.jsxs("div", {
                    className: "flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50",
                    children: [c.jsx(ja, {className: "h-4 w-4 text-indigo-600"}), c.jsx("div", {
                        className: "font-medium",
                        children: "Quick Replies Message"
                    })]
                }), c.jsxs("div", {
                    className: "p-4 space-y-4",
                    children: [c.jsxs("div", {
                        className: "space-y-2",
                        children: [c.jsx(Se, {
                            htmlFor: `${t}-header`,
                            children: "Header"
                        }), c.jsx(oc, {
                            id: `${t}-header`,
                            placeholder: "Enter header text",
                            value: s.header,
                            onChange: y => a("header", y)
                        })]
                    }), c.jsxs("div", {
                        className: "space-y-2",
                        children: [c.jsx(Se, {htmlFor: `${t}-body`, children: "Body"}), c.jsx(Ya, {
                            value: s.body,
                            onChange: y => a("body", y),
                            placeholder: "Enter message body"
                        })]
                    }), c.jsxs("div", {
                        className: "space-y-2",
                        children: [c.jsx(Se, {
                            htmlFor: `${t}-footer`,
                            children: "Footer"
                        }), c.jsx(oc, {
                            id: `${t}-footer`,
                            placeholder: "Enter footer text",
                            value: s.footer,
                            onChange: y => a("footer", y)
                        })]
                    }), c.jsxs("div", {
                        className: "space-y-3", children: [Array.from({length: s.activeButtons}).map((y, w) => {
                            const v = w + 1;
                            return c.jsxs("div", {
                                className: "relative",
                                children: [c.jsxs("div", {
                                    className: "flex items-start gap-2",
                                    children: [c.jsxs("div", {
                                        className: "flex-1",
                                        children: [c.jsxs(Se, {
                                            htmlFor: `${t}-button-${v}`,
                                            children: ["Button ", v]
                                        }), c.jsx(oc, {
                                            id: `${t}-button-${v}`,
                                            placeholder: `Enter button ${v} text`,
                                            value: s[`button${v}`],
                                            onChange: C => a(`button${v}`, C)
                                        })]
                                    }), s.activeButtons > 1 && c.jsx(le, {
                                        variant: "ghost",
                                        size: "icon",
                                        className: "mt-6",
                                        onClick: () => u(v),
                                        children: c.jsx(Zs, {className: "h-4 w-4"})
                                    })]
                                }), c.jsx(de, {
                                    type: "source",
                                    position: q.Right,
                                    id: `button-${v}`,
                                    className: "!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white",
                                    style: {right: "-20px", top: "50%", transform: "translateY(-50%)", zIndex: 50},
                                    isConnectable: r
                                })]
                            }, v)
                        }), s.activeButtons < 3 && c.jsxs(le, {
                            variant: "outline",
                            className: "w-full",
                            onClick: l,
                            children: [c.jsx(Pa, {className: "h-4 w-4 mr-2"}), "Add Button"]
                        }), c.jsxs("div", {
                            className: "mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2",
                            children: [c.jsxs("div", {
                                className: "flex flex-col items-end",
                                children: [c.jsx("span", {
                                    className: "text-xs text-gray-500",
                                    children: "Else exit"
                                }), c.jsx("span", {
                                    className: "text-[10px] text-gray-400",
                                    children: "Triggered when user reply is not known option"
                                })]
                            }), c.jsx(de, {
                                type: "source",
                                position: q.Right,
                                id: "else",
                                className: "!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white",
                                style: {
                                    position: "relative",
                                    right: "-8px",
                                    transform: "translateY(0)",
                                    display: "inline-block"
                                },
                                isConnectable: r
                            })]
                        })]
                    })]
                })]
            })
        }), c.jsx(ir, {
            children: c.jsxs(ar, {
                className: "text-red-600 focus:text-red-600 focus:bg-red-100",
                onClick: () => o(t),
                children: [c.jsx(at, {className: "mr-2 h-4 w-4"}), "Delete"]
            })
        })]
    })
}, X6 = ({data: e, id: t}) => {
    var s;
    const {updateNode: n} = rt(), r = ((s = e.settings) == null ? void 0 : s.openai) || {
        model: "gpt-4o-mini",
        prompt: "",
        systemPrompt: "",
        temperature: .7,
        maxTokens: 1e3,
        responseHandling: "reply",
        variableName: ""
    }, o = (i, a) => {
        n(t, {settings: {...e.settings, openai: {...r, [i]: a}}})
    };
    return c.jsxs(lr, {
        id: t,
        title: "AI Text Generation",
        icon: c.jsx(Vj, {className: "h-4 w-4 text-ai-vivid"}),
        headerBackground: "bg-gradient-to-r from-purple-900/70 via-purple-800/70 to-indigo-900/70",
        borderColor: "border-transparent",
        nodeWidth: "w-[350px]",
        className: "relative overflow-hidden animate-pulse-subtle rainbow-border",
        children: [c.jsx("div", {className: "absolute inset-x-0 h-px top-0 bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-70"}), c.jsx("div", {className: "absolute inset-x-0 h-px bottom-0 bg-gradient-to-r from-transparent via-indigo-500 to-transparent opacity-70"}), c.jsx("div", {className: "absolute -inset-1 opacity-20 bg-gradient-to-r from-purple-600 via-ai-vivid to-ai-magenta animate-gradient-x blur-xl"}), c.jsxs("div", {
            className: "relative z-10 space-y-4",
            children: [c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {className: "text-white/90", children: "Prompt"}), c.jsx(Ka, {
                    value: r.prompt,
                    onChange: i => o("prompt", i.target.value),
                    placeholder: "Enter the prompt...",
                    className: "min-h-[80px] bg-gray-900/50 backdrop-blur-md border-purple-500/30 text-white/90 placeholder:text-white/40"
                })]
            }), c.jsxs("div", {
                className: "space-y-2",
                children: [c.jsx(Se, {className: "text-white/90", children: "Choose AI Model"}), c.jsxs("div", {
                    className: "flex flex-wrap gap-2",
                    children: [c.jsxs(le, {
                        variant: "outline",
                        className: "relative group overflow-hidden bg-gray-900 text-white hover:bg-gray-800 px-4 py-1.5 h-auto text-xs rounded-full border-ai-purple/30",
                        children: [c.jsx("span", {
                            className: "relative z-10",
                            children: "+ Add models"
                        }), c.jsx("div", {className: "absolute inset-0 opacity-0 group-hover:opacity-100 bg-gradient-to-r from-ai-purple/30 via-ai-vivid/30 to-ai-magenta/30 blur-sm transition-opacity"})]
                    }), c.jsxs("div", {
                        className: "relative group",
                        children: [c.jsxs(le, {
                            variant: "outline",
                            className: "relative overflow-hidden bg-gradient-to-r from-ai-purple/20 to-ai-magenta/20 hover:from-ai-purple/40 hover:to-ai-magenta/40 border-purple-500/50 text-white px-4 py-1.5 h-auto text-xs rounded-full animate-shimmer bg-[length:200%_auto]",
                            children: [c.jsx("span", {
                                className: "relative z-10 font-semibold",
                                children: "GPT-4o"
                            }), c.jsx("span", {className: "absolute inset-0 bg-gradient-to-r from-ai-purple/10 via-ai-vivid/10 to-ai-magenta/10"})]
                        }), c.jsx("div", {className: "absolute -inset-px rounded-full opacity-0 group-hover:opacity-100 bg-ai-vivid blur transition-opacity duration-500 group-hover:animate-ai-glow"})]
                    })]
                })]
            }), c.jsxs("div", {
                className: "space-y-2 pt-1",
                children: [c.jsx(Se, {
                    className: "text-white/90",
                    children: "Temperature"
                }), c.jsxs("div", {
                    className: "flex items-center gap-2",
                    children: [c.jsx(De, {
                        type: "range",
                        min: "0",
                        max: "1",
                        step: "0.1",
                        value: r.temperature,
                        onChange: i => o("temperature", parseFloat(i.target.value)),
                        className: "bg-gray-900/50 h-2 accent-ai-vivid"
                    }), c.jsx("span", {
                        className: "text-xs text-white/80 min-w-[30px] text-right",
                        children: r.temperature
                    })]
                })]
            })]
        })]
    })
}, q6 = ({id: e, data: t, selected: n}) => c.jsx(lr, {
    id: e,
    title: t.label,
    icon: c.jsx(gu, {className: "h-4 w-4 text-cyan-600"}),
    headerBackground: "bg-cyan-50",
    borderColor: "border-cyan-200",
    children: c.jsx("p", {className: "text-xs text-gray-600", children: "Configure variable settings"})
}), Q6 = {
    trigger: lF,
    action: FF,
    end: zF,
    incomingMessage: BF,
    keyword_trigger: HF,
    opening_hours: WF,
    template: A6,
    webhook: I6,
    branch: D6,
    wait: $6,
    http: B6,
    message: V6,
    media: U6,
    question: bE,
    image: W6,
    pdf: K6,
    video: Y6,
    quick_replies: G6,
    openai: X6,
    datastore: q6
}, Z6 = Am, J6 = uE, ez = Im, CE = g.forwardRef(({
                                                     className: e,
                                                     ...t
                                                 }, n) => c.jsx(Ha, {
    className: se("fixed inset-0 z-50 bg-black/80  data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0", e), ...t,
    ref: n
}));
CE.displayName = Ha.displayName;
const tz = mu("fixed z-50 gap-4 bg-background p-6 shadow-lg transition ease-in-out data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-300 data-[state=open]:duration-500", {
    variants: {
        side: {
            top: "inset-x-0 top-0 border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top",
            bottom: "inset-x-0 bottom-0 border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom",
            left: "inset-y-0 left-0 h-full w-3/4 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left sm:max-w-sm",
            right: "inset-y-0 right-0 h-full w-3/4  border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm"
        }
    }, defaultVariants: {side: "right"}
}), SE = g.forwardRef(({
                           side: e = "right",
                           className: t,
                           children: n,
                           ...r
                       }, o) => c.jsxs(ez, {
    children: [c.jsx(CE, {}), c.jsx(Ua, {
        ref: o,
        className: se(tz({side: e}), t), ...r,
        children: n
    })]
}));
SE.displayName = Ua.displayName;
const nz = g.forwardRef(({className: e, ...t}, n) => c.jsx(Qu, {
    ref: n,
    className: se("text-lg font-semibold text-foreground", e), ...t
}));
nz.displayName = Qu.displayName;
const rz = g.forwardRef(({className: e, ...t}, n) => c.jsx(Zu, {
    ref: n,
    className: se("text-sm text-muted-foreground", e), ...t
}));
rz.displayName = Zu.displayName;
var Bm = "Tabs", [oz, _z] = mn(Bm, [Wu]), NE = Wu(), [sz, Vm] = oz(Bm), EE = g.forwardRef((e, t) => {
    const {
        __scopeTabs: n,
        value: r,
        onValueChange: o,
        defaultValue: s,
        orientation: i = "horizontal",
        dir: a,
        activationMode: l = "automatic",
        ...u
    } = e, d = Hu(a), [f, p] = To({prop: r, onChange: o, defaultProp: s});
    return c.jsx(sz, {
        scope: n,
        baseId: ct(),
        value: f,
        onValueChange: p,
        orientation: i,
        dir: d,
        activationMode: l,
        children: c.jsx(re.div, {dir: d, "data-orientation": i, ...u, ref: t})
    })
});
EE.displayName = Bm;
var kE = "TabsList", _E = g.forwardRef((e, t) => {
    const {__scopeTabs: n, loop: r = !0, ...o} = e, s = Vm(kE, n), i = NE(n);
    return c.jsx(hS, {
        asChild: !0, ...i,
        orientation: s.orientation,
        dir: s.dir,
        loop: r,
        children: c.jsx(re.div, {role: "tablist", "aria-orientation": s.orientation, ...o, ref: t})
    })
});
_E.displayName = kE;
var jE = "TabsTrigger", PE = g.forwardRef((e, t) => {
    const {__scopeTabs: n, value: r, disabled: o = !1, ...s} = e, i = Vm(jE, n), a = NE(n), l = RE(i.baseId, r),
        u = AE(i.baseId, r), d = r === i.value;
    return c.jsx(mS, {
        asChild: !0, ...a,
        focusable: !o,
        active: d,
        children: c.jsx(re.button, {
            type: "button",
            role: "tab",
            "aria-selected": d,
            "aria-controls": u,
            "data-state": d ? "active" : "inactive",
            "data-disabled": o ? "" : void 0,
            disabled: o,
            id: l, ...s,
            ref: t,
            onMouseDown: J(e.onMouseDown, f => {
                !o && f.button === 0 && f.ctrlKey === !1 ? i.onValueChange(r) : f.preventDefault()
            }),
            onKeyDown: J(e.onKeyDown, f => {
                [" ", "Enter"].includes(f.key) && i.onValueChange(r)
            }),
            onFocus: J(e.onFocus, () => {
                const f = i.activationMode !== "manual";
                !d && !o && f && i.onValueChange(r)
            })
        })
    })
});
PE.displayName = jE;
var TE = "TabsContent", ME = g.forwardRef((e, t) => {
    const {__scopeTabs: n, value: r, forceMount: o, children: s, ...i} = e, a = Vm(TE, n), l = RE(a.baseId, r),
        u = AE(a.baseId, r), d = r === a.value, f = g.useRef(d);
    return g.useEffect(() => {
        const p = requestAnimationFrame(() => f.current = !1);
        return () => cancelAnimationFrame(p)
    }, []), c.jsx(Dt, {
        present: o || d,
        children: ({present: p}) => c.jsx(re.div, {
            "data-state": d ? "active" : "inactive",
            "data-orientation": a.orientation,
            role: "tabpanel",
            "aria-labelledby": l,
            hidden: !p,
            id: u,
            tabIndex: 0, ...i,
            ref: t,
            style: {...e.style, animationDuration: f.current ? "0s" : void 0},
            children: p && s
        })
    })
});
ME.displayName = TE;

function RE(e, t) {
    return `${e}-trigger-${t}`
}

function AE(e, t) {
    return `${e}-content-${t}`
}

var iz = EE, IE = _E, OE = PE, DE = ME;
const az = iz, $E = g.forwardRef(({className: e, ...t}, n) => c.jsx(IE, {
    ref: n,
    className: se("inline-flex h-10 items-center justify-center rounded-md bg-muted p-1 text-muted-foreground", e), ...t
}));
$E.displayName = IE.displayName;
const Lp = g.forwardRef(({className: e, ...t}, n) => c.jsx(OE, {
    ref: n,
    className: se("inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm", e), ...t
}));
Lp.displayName = OE.displayName;
const Fp = g.forwardRef(({className: e, ...t}, n) => c.jsx(DE, {
    ref: n,
    className: se("mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2", e), ...t
}));
Fp.displayName = DE.displayName;
const lz = ({open: e, onOpenChange: t}) => {
    const [n, r] = g.useState(""), [o, s] = g.useState(!1), [i, a] = g.useState(""), [l, u] = g.useState(""), [d, f] = g.useState([{
        id: "faq-1",
        question: "What are your business hours?",
        answer: "We're open Monday through Friday from 9am to 5pm EST."
    }, {
        id: "faq-2",
        question: "How do I reset my password?",
        answer: "You can reset your password by clicking on the 'Forgot Password' link on the login page and following the instructions sent to your email."
    }, {
        id: "faq-3",
        question: "Do you offer refunds?",
        answer: "Yes, we offer full refunds within 30 days of purchase if you're not satisfied with our product."
    }]), [p, h] = g.useState([{id: "web-1", url: "https://example.com/support"}, {
        id: "web-2",
        url: "https://example.com/faq"
    }]), [x, m] = g.useState([{id: "file-1", name: "Product_Manual.pdf", type: "PDF"}, {
        id: "file-2",
        name: "Training_Guide.docx",
        type: "DOCX"
    }, {id: "file-3", name: "Pricing_Table.xlsx", type: "XLSX"}]), b = () => {
        n && (s(!0), setTimeout(() => {
            const N = {id: Date.now().toString(), url: n};
            h([...p, N]), s(!1), r("")
        }, 1500))
    }, y = () => {
        if (!i.trim() || !l.trim()) return;
        const N = {id: Date.now().toString(), question: i, answer: l};
        f([...d, N]), a(""), u("")
    }, w = N => {
        f(d.filter(E => E.id !== N))
    }, v = N => {
        h(p.filter(E => E.id !== N))
    }, C = N => {
        m(x.filter(E => E.id !== N))
    }, S = () => {
        const N = ["PDF", "DOCX", "TXT", "CSV", "PPTX"], E = N[Math.floor(Math.random() * N.length)],
            T = Math.floor(Math.random() * 1e3),
            O = {id: Date.now().toString(), name: `Document-${T}.${E.toLowerCase()}`, type: E};
        m([...x, O])
    };
    return c.jsx(Z6, {
        open: e, onOpenChange: t, children: c.jsx(SE, {
            className: "w-[400px] sm:w-[540px] p-0 border-l", children: c.jsxs("div", {
                className: "flex flex-col h-full",
                children: [c.jsxs("div", {
                    className: "flex items-center justify-between border-b p-4",
                    children: [c.jsxs("div", {
                        className: "flex items-center gap-2",
                        children: [c.jsx(Qw, {className: "h-5 w-5 text-purple-600"}), c.jsx("h2", {
                            className: "text-lg font-medium",
                            children: "AI Bot Training"
                        })]
                    }), c.jsx(J6, {
                        asChild: !0,
                        children: c.jsx(le, {
                            variant: "ghost",
                            size: "icon",
                            onClick: () => t(!1),
                            children: c.jsx(Zs, {className: "h-4 w-4"})
                        })
                    })]
                }), c.jsx("div", {
                    className: "flex-1 overflow-auto p-4", children: c.jsxs(az, {
                        defaultValue: "training",
                        className: "w-full",
                        children: [c.jsxs($E, {
                            className: "grid grid-cols-2 mb-4",
                            children: [c.jsx(Lp, {value: "training", children: "Training"}), c.jsx(Lp, {
                                value: "data",
                                children: "Data"
                            })]
                        }), c.jsxs(Fp, {
                            value: "training",
                            className: "space-y-4",
                            children: [c.jsxs("div", {
                                className: "rounded-lg border p-4",
                                children: [c.jsx("h3", {
                                    className: "font-medium mb-2",
                                    children: "Website Training"
                                }), c.jsx("p", {
                                    className: "text-sm text-muted-foreground mb-3",
                                    children: "Train your bot using content from websites."
                                }), c.jsxs("div", {
                                    className: "flex gap-2",
                                    children: [c.jsx(De, {
                                        placeholder: "https://example.com",
                                        value: n,
                                        onChange: N => r(N.target.value),
                                        className: "text-sm"
                                    }), c.jsxs(le, {
                                        size: "sm",
                                        onClick: b,
                                        disabled: !n || o,
                                        children: [c.jsx(ra, {className: "h-4 w-4 mr-2"}), o ? "Processing..." : "Add"]
                                    })]
                                })]
                            }), c.jsxs("div", {
                                className: "rounded-lg border p-4",
                                children: [c.jsx("h3", {
                                    className: "font-medium mb-2",
                                    children: "Upload Files"
                                }), c.jsx("p", {
                                    className: "text-sm text-muted-foreground mb-3",
                                    children: "Upload documents, PDFs or text files to train your bot."
                                }), c.jsxs(le, {
                                    variant: "outline",
                                    size: "sm",
                                    className: "w-full",
                                    onClick: S,
                                    children: [c.jsx(Co, {className: "h-4 w-4 mr-2"}), "Upload Files"]
                                })]
                            }), c.jsxs("div", {
                                className: "rounded-lg border p-4",
                                children: [c.jsx("h3", {
                                    className: "font-medium mb-2",
                                    children: "FAQ Training"
                                }), c.jsx("p", {
                                    className: "text-sm text-muted-foreground mb-3",
                                    children: "Add frequently asked questions to train your bot."
                                }), d.length > 0 && c.jsx("div", {
                                    className: "space-y-3 mb-4",
                                    children: d.map(N => c.jsxs("div", {
                                        className: "bg-muted rounded-md p-3 relative",
                                        children: [c.jsx("div", {
                                            className: "font-medium",
                                            children: N.question
                                        }), c.jsx("div", {
                                            className: "text-sm text-muted-foreground mt-1",
                                            children: N.answer
                                        }), c.jsx(le, {
                                            variant: "ghost",
                                            size: "icon",
                                            className: "absolute top-2 right-2 h-6 w-6",
                                            onClick: () => w(N.id),
                                            children: c.jsx(at, {className: "h-3.5 w-3.5"})
                                        })]
                                    }, N.id))
                                }), c.jsxs("div", {
                                    className: "space-y-2 mt-2",
                                    children: [c.jsxs("div", {
                                        children: [c.jsx(Se, {
                                            htmlFor: "question",
                                            className: "text-xs",
                                            children: "Question"
                                        }), c.jsx(De, {
                                            id: "question",
                                            placeholder: "Enter a question",
                                            value: i,
                                            onChange: N => a(N.target.value),
                                            className: "text-sm h-8"
                                        })]
                                    }), c.jsxs("div", {
                                        children: [c.jsx(Se, {
                                            htmlFor: "answer",
                                            className: "text-xs",
                                            children: "Answer"
                                        }), c.jsx(De, {
                                            id: "answer",
                                            placeholder: "Enter the answer",
                                            value: l,
                                            onChange: N => u(N.target.value),
                                            className: "text-sm h-8"
                                        })]
                                    }), c.jsxs(le, {
                                        size: "sm",
                                        variant: "outline",
                                        className: "w-full",
                                        onClick: y,
                                        disabled: !i.trim() || !l.trim(),
                                        children: [c.jsx(bo, {className: "h-4 w-4 mr-2"}), "Add FAQ"]
                                    })]
                                })]
                            })]
                        }), c.jsxs(Fp, {
                            value: "data",
                            className: "space-y-4",
                            children: [c.jsxs("div", {
                                className: "rounded-lg border p-4",
                                children: [c.jsx("h3", {
                                    className: "font-medium mb-2",
                                    children: "Trained Websites"
                                }), p.length === 0 ? c.jsx("p", {
                                    className: "text-sm text-muted-foreground",
                                    children: "No websites have been added for training."
                                }) : c.jsx("div", {
                                    className: "space-y-2",
                                    children: p.map(N => c.jsxs("div", {
                                        className: "bg-muted rounded-md p-3 flex justify-between items-center",
                                        children: [c.jsxs("div", {
                                            className: "flex items-center",
                                            children: [c.jsx(ra, {className: "h-4 w-4 text-blue-500 mr-2"}), c.jsx("span", {
                                                className: "text-sm",
                                                children: N.url
                                            })]
                                        }), c.jsx(le, {
                                            variant: "ghost",
                                            size: "icon",
                                            className: "h-7 w-7",
                                            onClick: () => v(N.id),
                                            children: c.jsx(at, {className: "h-3.5 w-3.5"})
                                        })]
                                    }, N.id))
                                })]
                            }), c.jsxs("div", {
                                className: "rounded-lg border p-4",
                                children: [c.jsx("h3", {
                                    className: "font-medium mb-2",
                                    children: "Trained Files"
                                }), x.length === 0 ? c.jsx("p", {
                                    className: "text-sm text-muted-foreground",
                                    children: "No files have been uploaded for training."
                                }) : c.jsx("div", {
                                    className: "space-y-2",
                                    children: x.map(N => c.jsxs("div", {
                                        className: "bg-muted rounded-md p-3 flex justify-between items-center",
                                        children: [c.jsxs("div", {
                                            className: "flex items-center",
                                            children: [c.jsx(bo, {className: "h-4 w-4 text-amber-500 mr-2"}), c.jsx("span", {
                                                className: "text-sm",
                                                children: N.name
                                            })]
                                        }), c.jsx(le, {
                                            variant: "ghost",
                                            size: "icon",
                                            className: "h-7 w-7",
                                            onClick: () => C(N.id),
                                            children: c.jsx(at, {className: "h-3.5 w-3.5"})
                                        })]
                                    }, N.id))
                                })]
                            }), c.jsxs("div", {
                                className: "rounded-lg border p-4",
                                children: [c.jsx("h3", {
                                    className: "font-medium mb-2",
                                    children: "Trained FAQs"
                                }), d.length === 0 ? c.jsx("p", {
                                    className: "text-sm text-muted-foreground",
                                    children: "No FAQs have been added for training."
                                }) : c.jsx("div", {
                                    className: "space-y-2",
                                    children: d.map(N => c.jsxs("div", {
                                        className: "bg-muted rounded-md p-3 relative pr-8",
                                        children: [c.jsx("div", {
                                            className: "font-medium text-sm",
                                            children: N.question
                                        }), c.jsx("div", {
                                            className: "text-xs text-muted-foreground mt-1",
                                            children: N.answer
                                        }), c.jsx(le, {
                                            variant: "ghost",
                                            size: "icon",
                                            className: "absolute top-2 right-2 h-6 w-6",
                                            onClick: () => w(N.id),
                                            children: c.jsx(at, {className: "h-3.5 w-3.5"})
                                        })]
                                    }, N.id))
                                })]
                            })]
                        })]
                    })
                })]
            })
        })
    })
};
var ay, ly;
const LE = ((ly = (ay = window.data) == null ? void 0 : ay.flow) == null ? void 0 : ly.flow_data) || "{}";
var cy;
const FE = ((cy = JSON.parse(LE)) == null ? void 0 : cy.nodes) || [];
var uy;
const zE = ((uy = JSON.parse(LE)) == null ? void 0 : uy.edges) || [], cz = {animated: !0, style: {stroke: "#000000"}};
console.log("Initial nodes:", FE);
console.log("Initial edges:", zE);
console.log("========= Window data ========");
console.log(window.data);
const uz = ({flowId: e = "1"}) => {
    const [t, n, r] = qO(FE), [o, s, i] = QO(zE), [a, l] = g.useState(!1), {toast: u} = Oh(), d = g.useCallback(m => {
        const b = {...m, id: `e${Date.now()}`};
        return s(y => Wb(b, y))
    }, [s]), f = async () => {
        const b = {
            nodes: t.map(y => {
                var w;
                return {...y, data: {...y.data, settings: ((w = y.data) == null ? void 0 : w.settings) || {}}}
            }), edges: o
        };
        console.log("Saving flow:", b), console.log("Flow ID:", window.data.flow.id);
        try {
            if (!(await fetch(`/flowmaker/update/${window.data.flow.id}`, {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(b)
            })).ok) throw new Error("Failed to save flow");
            u({title: "Flow saved", description: "Your flow has been saved successfully."})
        } catch (y) {
            console.error("Error saving flow:", y), u({
                title: "Save failed",
                description: "Failed to save flow. Please try again.",
                variant: "destructive"
            })
        }
    }, p = m => {
    }, h = () => {
        l(!0)
    }, x = () => {
        window.location.href = "/flows"
    };
    return c.jsxs("div", {
        className: "flex h-screen bg-[#F1F0FB] relative",
        children: [c.jsx("div", {
            className: "absolute left-0 top-0 m-2.5 z-10",
            children: c.jsx("div", {
                className: "w-20 flex flex-col items-center py-4 space-y-6",
                children: c.jsx("div", {
                    className: "flex flex-col items-center space-y-6",
                    children: c.jsx(O$, {
                        onImportClick: () => {
                        }, onPopoverIndexChange: p, onOpenDataSidebar: h
                    })
                })
            })
        }), c.jsxs("div", {
            className: "flex-1 relative",
            children: [c.jsxs("div", {
                className: "absolute top-4 right-4 z-10 flex space-x-2",
                children: [c.jsxs(le, {
                    onClick: x,
                    variant: "outline",
                    size: "default",
                    className: "gap-2 bg-white",
                    children: [c.jsx(Bj, {className: "h-4 w-4"}), "Back"]
                }), c.jsxs(le, {
                    onClick: f,
                    variant: "outline",
                    size: "default",
                    className: "gap-2 bg-white",
                    children: [c.jsx(Zj, {className: "h-4 w-4"}), "Save"]
                })]
            }), c.jsx(XO, {
                nodes: t,
                edges: o,
                onNodesChange: r,
                onEdgesChange: i,
                onConnect: d,
                nodeTypes: Q6,
                defaultEdgeOptions: cz,
                minZoom: .1,
                maxZoom: 4,
                fitView: !0,
                children: c.jsx(nD, {})
            }), c.jsx(lz, {open: a, onOpenChange: l})]
        })]
    })
}, dz = e => c.jsx(FC, {children: c.jsx(uz, {})}), fz = new dR;
console.log("System is running");
const pz = e => c.jsx(pR, {
    client: fz,
    children: c.jsxs(BM, {children: [c.jsx($P, {}), c.jsx(mT, {}), c.jsx(dz, {})]})
}), hz = document.getElementById("flow"), mz = Nw(hz);
mz.render(c.jsx(g.StrictMode, {children: c.jsx(pz, {})}));
