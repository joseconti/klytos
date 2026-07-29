/* @ds-bundle: {"format":4,"namespace":"PackDeskDesignSystem_9c54ce","components":[{"name":"AppIcon","sourcePath":"components/brand/AppIcon.jsx"},{"name":"Logo","sourcePath":"components/brand/Logo.jsx"},{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"Input","sourcePath":"components/core/Input.jsx"},{"name":"Avatar","sourcePath":"components/data/Avatar.jsx"},{"name":"Chip","sourcePath":"components/data/Chip.jsx"},{"name":"ListRow","sourcePath":"components/data/ListRow.jsx"},{"name":"StatCard","sourcePath":"components/data/StatCard.jsx"},{"name":"Table","sourcePath":"components/data/Table.jsx"},{"name":"EmptyState","sourcePath":"components/feedback/EmptyState.jsx"},{"name":"Progress","sourcePath":"components/feedback/Progress.jsx"},{"name":"Skeleton","sourcePath":"components/feedback/Skeleton.jsx"},{"name":"Spinner","sourcePath":"components/feedback/Spinner.jsx"},{"name":"Toast","sourcePath":"components/feedback/Toast.jsx"},{"name":"Checkbox","sourcePath":"components/forms/Checkbox.jsx"},{"name":"DatePicker","sourcePath":"components/forms/DatePicker.jsx"},{"name":"FileUpload","sourcePath":"components/forms/FileUpload.jsx"},{"name":"Radio","sourcePath":"components/forms/Radio.jsx"},{"name":"SearchField","sourcePath":"components/forms/SearchField.jsx"},{"name":"Select","sourcePath":"components/forms/Select.jsx"},{"name":"Slider","sourcePath":"components/forms/Slider.jsx"},{"name":"Stepper","sourcePath":"components/forms/Stepper.jsx"},{"name":"Switch","sourcePath":"components/forms/Switch.jsx"},{"name":"Textarea","sourcePath":"components/forms/Textarea.jsx"},{"name":"Breadcrumb","sourcePath":"components/navigation/Breadcrumb.jsx"},{"name":"Menu","sourcePath":"components/navigation/Menu.jsx"},{"name":"Pagination","sourcePath":"components/navigation/Pagination.jsx"},{"name":"SegmentedControl","sourcePath":"components/navigation/SegmentedControl.jsx"},{"name":"Tabs","sourcePath":"components/navigation/Tabs.jsx"},{"name":"Banner","sourcePath":"components/overlays/Banner.jsx"},{"name":"Dialog","sourcePath":"components/overlays/Dialog.jsx"},{"name":"Popover","sourcePath":"components/overlays/Popover.jsx"},{"name":"Sheet","sourcePath":"components/overlays/Sheet.jsx"},{"name":"Tooltip","sourcePath":"components/overlays/Tooltip.jsx"}],"sourceHashes":{"components/brand/AppIcon.jsx":"1a9f537da86f","components/brand/Logo.jsx":"4bd7045a36b9","components/core/Badge.jsx":"67e983ef6761","components/core/Button.jsx":"0d19993e0b1d","components/core/Card.jsx":"836d2cce1911","components/core/Input.jsx":"ff5f9104c580","components/data/Avatar.jsx":"61cab9147303","components/data/Chip.jsx":"22aeaddeaab4","components/data/ListRow.jsx":"57a0a51cac75","components/data/StatCard.jsx":"6dc4e2975c8e","components/data/Table.jsx":"9d03ff835aa0","components/feedback/EmptyState.jsx":"569cf4338aee","components/feedback/Progress.jsx":"b3897b1476f5","components/feedback/Skeleton.jsx":"20d051754bf0","components/feedback/Spinner.jsx":"ff31b76d44ef","components/feedback/Toast.jsx":"3d8baebba2d4","components/forms/Checkbox.jsx":"906ad3f74c30","components/forms/DatePicker.jsx":"b7944d952233","components/forms/FileUpload.jsx":"3315df613939","components/forms/Radio.jsx":"10cba8dbe623","components/forms/SearchField.jsx":"ce41ce78edb7","components/forms/Select.jsx":"627b7d7f3507","components/forms/Slider.jsx":"151441b0f369","components/forms/Stepper.jsx":"798f00981c8d","components/forms/Switch.jsx":"314037c4dbd3","components/forms/Textarea.jsx":"5f548322ca30","components/navigation/Breadcrumb.jsx":"bf8665bff906","components/navigation/Menu.jsx":"eb8081e0cad6","components/navigation/Pagination.jsx":"c642eaccaecc","components/navigation/SegmentedControl.jsx":"b7a214a9fd44","components/navigation/Tabs.jsx":"60a1d64eb9f5","components/overlays/Banner.jsx":"ec020f049a4d","components/overlays/Dialog.jsx":"763962e523fc","components/overlays/Popover.jsx":"04739676ec07","components/overlays/Sheet.jsx":"44c5cf1da9e8","components/overlays/Tooltip.jsx":"b2b48e95fcc2"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.PackDeskDesignSystem_9c54ce = window.PackDeskDesignSystem_9c54ce || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/brand/AppIcon.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* ============================================================
   AppIcon — marca de producto de PackDesk · FUENTE ÚNICA.
   Geometría idéntica a los assets canónicos (assets/favicon.svg,
   assets/icono-packdesk.svg): grid de 120 u, figura blanca
   centrada en (60,60) — etiqueta 46×46 (≈38 % del lado) girada
   45° + agujero + check. Para mantener la COHERENCIA de toda la
   familia, todo icono se dibuja con este componente; solo cambian
   contenedor (mask), tono, tamaño y —en un producto nuevo— la
   figura interior (`glyph`), conservando grid y proporción.
   ============================================================ */

const MASK_RX = {
  squircle: 27,
  tile: 12,
  web: 27
}; // radios sobre 120 u (circle = elipse)
const TONE_BG = {
  brand: "var(--color-acento)",
  graphite: "#2C2C2E",
  mono: "var(--texto-primario)"
};
let __pdIconSeq = 0;

/**
 * Icono de producto de PackDesk. `mask` adapta el contenedor por
 * plataforma; `tone` el fondo; `gradient` activa el degradado "hero";
 * `glyph` sustituye la figura interior para un producto de la familia.
 */
function AppIcon({
  size = 64,
  mask = "squircle",
  tone = "brand",
  gradient = false,
  glyph = null,
  style,
  ...rest
}) {
  const solid = TONE_BG[tone] || TONE_BG.brand;
  const gid = "pdai" + ++__pdIconSeq;
  const fill = gradient ? "url(#" + gid + ")" : solid;
  const cut = gradient ? "#0B6B61" : solid; // agujero + check recortan la figura al color del fondo
  return /*#__PURE__*/React.createElement("svg", _extends({
    width: size,
    height: size,
    viewBox: "0 0 120 120",
    style: style,
    "aria-hidden": "true"
  }, rest), gradient ? /*#__PURE__*/React.createElement("defs", null, /*#__PURE__*/React.createElement("linearGradient", {
    id: gid,
    x1: "0",
    y1: "0",
    x2: "0",
    y2: "1"
  }, /*#__PURE__*/React.createElement("stop", {
    offset: "0",
    stopColor: "#15968A"
  }), /*#__PURE__*/React.createElement("stop", {
    offset: "1",
    stopColor: "#0B6B61"
  }))) : null, mask === "circle" ? /*#__PURE__*/React.createElement("circle", {
    cx: "60",
    cy: "60",
    r: "60",
    fill: fill
  }) : /*#__PURE__*/React.createElement("rect", {
    width: "120",
    height: "120",
    rx: MASK_RX[mask] || 27,
    fill: fill
  }), glyph != null ? glyph : /*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("g", {
    transform: "rotate(45 60 60)"
  }, /*#__PURE__*/React.createElement("rect", {
    x: "37",
    y: "37",
    width: "46",
    height: "46",
    rx: "11",
    fill: "#FFFFFF"
  })), /*#__PURE__*/React.createElement("circle", {
    cx: "60",
    cy: "41",
    r: "6.5",
    fill: cut
  }), /*#__PURE__*/React.createElement("path", {
    d: "M49 64 l9 9 16 -16",
    stroke: cut,
    strokeWidth: "7",
    fill: "none",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  })));
}
Object.assign(__ds_scope, { AppIcon });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/AppIcon.jsx", error: String((e && e.message) || e) }); }

// components/brand/Logo.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Wordmark de PackDesk (y de cualquier producto de la familia): bicolor Bold.
 * `first` en neutro (se adapta al fondo), `second` en acento. `icon` antepone el AppIcon.
 */
function Logo({
  first = "Pack",
  second = "Desk",
  size = 28,
  icon = false,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: Math.round(size * 0.34),
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), icon ? /*#__PURE__*/React.createElement(__ds_scope.AppIcon, {
    size: Math.round(size * 1.25)
  }) : null, /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 700,
      letterSpacing: "-0.035em",
      fontSize: size,
      lineHeight: 1
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--texto-primario)"
    }
  }, first), /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--color-acento)"
    }
  }, second)));
}
Object.assign(__ds_scope, { Logo });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/Logo.jsx", error: String((e && e.message) || e) }); }

// components/core/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONE = {
  info: "--color-info",
  exito: "--color-exito",
  aviso: "--color-aviso",
  peligro: "--color-peligro",
  offline: "--color-offline",
  sync: "--color-sync",
  conflicto: "--color-conflicto",
  reconectar: "--color-reconectar",
  acento: "--color-acento"
};
const TINT = {
  info: "--tinte-info",
  exito: "--tinte-exito",
  aviso: "--tinte-aviso",
  peligro: "--tinte-peligro",
  offline: "--tinte-offline",
  sync: "--tinte-sync",
  conflicto: "--tinte-conflicto",
  reconectar: "--tinte-reconectar",
  acento: "--tinte-acento"
};

/**
 * Píldora de estado. `soft` (tinte), `solid` (relleno, para severidad alta)
 * u `outline`. `dot` añade un punto del color del tono (estados de producto).
 */
function Badge({
  tone = "info",
  variant = "soft",
  dot = false,
  children,
  style,
  ...rest
}) {
  const color = `var(${TONE[tone] || TONE.info})`;
  const tint = `var(${TINT[tone] || TINT.info})`;
  const byVariant = {
    soft: {
      background: tint,
      color,
      border: "1px solid transparent"
    },
    solid: {
      background: color,
      color: "var(--sobre-acento)",
      border: "1px solid transparent"
    },
    outline: {
      background: "transparent",
      color,
      border: `1px solid ${color}`
    }
  };
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 6,
      fontFamily: "var(--font-ui)",
      fontSize: 11.5,
      fontWeight: 600,
      lineHeight: 1,
      padding: "4px 12px",
      borderRadius: "var(--radio-pildora)",
      ...(byVariant[variant] || byVariant.soft),
      ...style
    }
  }, rest), dot ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: 7,
      height: 7,
      borderRadius: 999,
      background: color
    }
  }) : null, children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Badge.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const SIZES = {
  sm: {
    padding: "5px 12px",
    fontSize: 12
  },
  md: {
    padding: "7px 16px",
    fontSize: 13
  },
  lg: {
    padding: "9px 20px",
    fontSize: 14
  }
};
const VARIANTS = {
  primary: {
    background: "var(--color-acento)",
    color: "var(--sobre-acento)"
  },
  secondary: {
    background: "var(--fondo-contenido)",
    color: "var(--texto-primario)",
    borderColor: "var(--separador)"
  },
  ghost: {
    background: "transparent",
    color: "var(--color-acento)"
  },
  destructive: {
    background: "var(--color-peligro)",
    color: "var(--sobre-acento)"
  }
};

/**
 * Botón de acción de PackDesk. La acción primaria usa el acento de marca
 * (una por vista). El foco muestra siempre el anillo de 2px en acento.
 */
function Button({
  variant = "primary",
  size = "md",
  icon = null,
  disabled = false,
  children,
  style,
  ...rest
}) {
  const sz = SIZES[size] || SIZES.md;
  return /*#__PURE__*/React.createElement("button", _extends({
    disabled: disabled,
    style: {
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      gap: 8,
      fontFamily: "var(--font-ui)",
      fontWeight: 500,
      lineHeight: 1.2,
      whiteSpace: "nowrap",
      padding: sz.padding,
      fontSize: sz.fontSize,
      borderRadius: "var(--radio-control)",
      border: "1px solid transparent",
      cursor: disabled ? "default" : "pointer",
      opacity: disabled ? 0.45 : 1,
      transition: "filter var(--dur-hover) var(--easing-estandar), opacity var(--dur-hover)",
      ...(VARIANTS[variant] || VARIANTS.primary),
      ...style
    }
  }, rest), icon, children);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Superficie contenedora. Fondo elevado, radius 10, sombra de tarjeta,
 * sin bordes de color. Es la unidad base de agrupación de contenido.
 */
function Card({
  elevated = false,
  padding = 16,
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      background: elevated ? "var(--fondo-elevado)" : "var(--fondo-contenido)",
      borderRadius: "var(--radio-card)",
      boxShadow: "var(--sombra-card)",
      padding,
      ...style
    }
  }, rest), children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Campo de texto con etiqueta y pista opcionales. `invalid` lo pinta en
 * peligro; `mono` usa la monoespaciada (SKU, importes, referencias).
 */
function Input({
  label = null,
  hint = null,
  invalid = false,
  mono = false,
  style,
  id,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: "block",
      fontFamily: "var(--font-ui)"
    }
  }, label ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      fontSize: 12,
      fontWeight: 600,
      marginBottom: 5,
      color: "var(--texto-primario)"
    }
  }, label) : null, /*#__PURE__*/React.createElement("input", _extends({
    id: id,
    style: {
      width: "100%",
      boxSizing: "border-box",
      fontFamily: mono ? "var(--font-mono)" : "var(--font-ui)",
      fontSize: 13,
      color: "var(--texto-primario)",
      background: "var(--fondo-contenido)",
      border: `1px solid ${invalid ? "var(--color-peligro)" : "var(--separador)"}`,
      borderRadius: "var(--radio-control)",
      padding: "7px 10px",
      outline: "none",
      ...style
    }
  }, rest)), hint ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      fontSize: 11,
      marginTop: 5,
      color: invalid ? "var(--color-peligro)" : "var(--texto-terciario)"
    }
  }, hint) : null);
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Input.jsx", error: String((e && e.message) || e) }); }

// components/data/Avatar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONO_AV = {
  acento: "var(--color-acento)",
  info: "var(--color-info)",
  exito: "var(--color-exito)",
  conflicto: "var(--color-conflicto)",
  neutro: "var(--fila-hover)"
};

/**
 * Avatar de iniciales. `neutro` para el resto de un grupo ("+2").
 * Con `stacked`, borde del fondo para solapar en grupo.
 */
function Avatar({
  initials = "",
  size = 26,
  tone = "acento",
  stacked = false,
  style,
  ...rest
}) {
  const neutro = tone === "neutro";
  return /*#__PURE__*/React.createElement("span", _extends({
    "aria-hidden": "true",
    style: {
      width: size,
      height: size,
      borderRadius: 999,
      flex: "0 0 auto",
      display: "grid",
      placeItems: "center",
      background: TONO_AV[tone] || TONO_AV.acento,
      color: neutro ? "var(--texto-secundario)" : "var(--sobre-acento)",
      fontFamily: "var(--font-ui)",
      fontSize: Math.round(size * 0.4),
      fontWeight: 700,
      border: stacked ? "2px solid var(--fondo-contenido)" : "none",
      boxSizing: "border-box",
      marginLeft: stacked ? -8 : 0,
      ...style
    }
  }, rest), initials);
}
Object.assign(__ds_scope, { Avatar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/data/Chip.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Chip/etiqueta de filtro. Seleccionado = tinte + texto acento.
 * `onRemove` añade la X de quitar.
 */
function Chip({
  selected = false,
  onRemove,
  onClick,
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({
    role: onClick ? "button" : undefined,
    tabIndex: onClick ? 0 : undefined,
    onClick: onClick,
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 6,
      fontSize: 11.5,
      fontWeight: 600,
      padding: "4px 11px",
      borderRadius: 999,
      background: selected ? "var(--tinte-acento)" : "var(--fila-hover)",
      color: selected ? "var(--color-acento)" : "var(--texto-secundario)",
      fontFamily: "var(--font-ui)",
      cursor: onClick ? "pointer" : "default",
      whiteSpace: "nowrap",
      ...style
    }
  }, rest), children, onRemove ? /*#__PURE__*/React.createElement("svg", {
    onClick: e => {
      e.stopPropagation();
      onRemove();
    },
    width: "11",
    height: "11",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2.4",
    strokeLinecap: "round",
    style: {
      cursor: "pointer"
    }
  }, /*#__PURE__*/React.createElement("path", {
    d: "M6 6l12 12M18 6L6 18"
  })) : null);
}
Object.assign(__ds_scope, { Chip });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Chip.jsx", error: String((e && e.message) || e) }); }

// components/data/ListRow.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Fila de lista móvil (título + subtítulo + elemento final).
 * Nº de pedido en mono; seleccionada con --fila-seleccion.
 */
function ListRow({
  title,
  subtitle = null,
  trailing = null,
  mono = true,
  selected = false,
  onClick,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "listitem",
    onClick: onClick,
    style: {
      display: "flex",
      alignItems: "center",
      gap: 12,
      padding: "0 16px",
      minHeight: 56,
      boxSizing: "border-box",
      borderBottom: "1px solid var(--separador)",
      fontFamily: "var(--font-ui)",
      cursor: onClick ? "pointer" : "default",
      background: selected ? "var(--fila-seleccion)" : "transparent",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: mono ? "var(--font-mono)" : "var(--font-ui)",
      fontWeight: 600,
      fontSize: 15,
      color: "var(--texto-primario)"
    }
  }, title), subtitle ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      color: "var(--texto-secundario)",
      marginTop: 1
    }
  }, subtitle) : null), trailing);
}
Object.assign(__ds_scope, { ListRow });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/ListRow.jsx", error: String((e && e.message) || e) }); }

// components/data/StatCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Tarjeta KPI: cifra grande en mono + etiqueta + delta opcional.
 */
function StatCard({
  value,
  label,
  delta = null,
  deltaTone = "exito",
  style,
  ...rest
}) {
  const COLOR = {
    exito: "var(--color-exito)",
    peligro: "var(--color-peligro)",
    conflicto: "var(--color-conflicto)",
    neutro: "var(--texto-terciario)"
  }[deltaTone] || "var(--texto-terciario)";
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      background: "var(--fondo-contenido)",
      borderRadius: "var(--radio-card)",
      boxShadow: "var(--sombra-card)",
      padding: "14px 16px",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 24,
      fontWeight: 600,
      letterSpacing: "-0.01em",
      color: "var(--texto-primario)"
    }
  }, value), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      color: "var(--texto-secundario)",
      marginTop: 3
    }
  }, label), delta ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 11,
      fontWeight: 600,
      marginTop: 6,
      color: COLOR
    }
  }, delta) : null);
}
Object.assign(__ds_scope, { StatCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/StatCard.jsx", error: String((e && e.message) || e) }); }

// components/data/Table.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Tabla de datos. `columns` define clave, etiqueta, alineado y mono;
 * fila seleccionada con --fila-seleccion; densidades 36/52/68.
 * Importes y cifras SIEMPRE en mono y a la derecha.
 */
function Table({
  columns = [],
  rows = [],
  selectedIndex = -1,
  density = 36,
  onRowClick,
  style,
  ...rest
}) {
  const template = columns.map(c => c.width || "1fr").join(" ");
  const cell = (c, v) => /*#__PURE__*/React.createElement("span", {
    style: {
      padding: "0 12px",
      fontFamily: c.mono ? "var(--font-mono)" : "var(--font-ui)",
      textAlign: c.align || "left",
      fontWeight: c.bold ? 600 : 400,
      overflow: "hidden",
      textOverflow: "ellipsis",
      whiteSpace: "nowrap"
    }
  }, v);
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "table",
    style: {
      background: "var(--fondo-contenido)",
      borderRadius: "var(--radio-card)",
      boxShadow: "var(--sombra-card)",
      overflow: "hidden",
      fontFamily: "var(--font-ui)",
      fontSize: 12.5,
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    role: "row",
    style: {
      display: "grid",
      gridTemplateColumns: template,
      alignItems: "center",
      height: 32,
      borderBottom: "1px solid var(--separador)",
      fontSize: 10.5,
      fontWeight: 600,
      color: "var(--texto-terciario)"
    }
  }, columns.map((c, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: i
  }, cell({
    ...c,
    mono: false,
    bold: false
  }, c.label)))), rows.map((r, ri) => /*#__PURE__*/React.createElement("div", {
    key: ri,
    role: "row",
    onClick: onRowClick ? () => onRowClick(ri) : undefined,
    style: {
      display: "grid",
      gridTemplateColumns: template,
      alignItems: "center",
      height: density,
      borderBottom: ri < rows.length - 1 ? "1px solid var(--separador)" : "none",
      cursor: onRowClick ? "pointer" : "default",
      background: ri === selectedIndex ? "var(--fila-seleccion)" : "transparent"
    }
  }, columns.map((c, ci) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: ci
  }, cell(c, r[c.key]))))));
}
Object.assign(__ds_scope, { Table });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Table.jsx", error: String((e && e.message) || e) }); }

// components/feedback/EmptyState.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Estado vacío: icono de línea + título + texto + acción.
 * Siempre explica qué pasará y ofrece el siguiente paso.
 */
function EmptyState({
  icon = null,
  title,
  description = null,
  action = null,
  onAction,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      display: "flex",
      flexDirection: "column",
      alignItems: "center",
      justifyContent: "center",
      textAlign: "center",
      padding: 22,
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), icon, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13.5,
      fontWeight: 700,
      color: "var(--texto-primario)",
      margin: "10px 0 4px"
    }
  }, title), description ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      lineHeight: 1.5,
      color: "var(--texto-secundario)",
      marginBottom: action ? 12 : 0,
      maxWidth: 320
    }
  }, description) : null, action ? /*#__PURE__*/React.createElement("button", {
    onClick: onAction,
    style: {
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      fontFamily: "var(--font-ui)",
      fontWeight: 500,
      fontSize: 12.5,
      padding: "7px 14px",
      borderRadius: "var(--radio-control)",
      border: "1px solid transparent",
      background: "var(--color-acento)",
      color: "var(--sobre-acento)",
      cursor: "pointer"
    }
  }, action) : null);
}
Object.assign(__ds_scope, { EmptyState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/EmptyState.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Progress.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Progreso determinado: barra (`kind="bar"`) o círculo (`kind="circle"`).
 * Valor 0–100; etiqueta + porcentaje en mono.
 */
function Progress({
  kind = "bar",
  value = 0,
  label = null,
  showValue = true,
  size = 34,
  style,
  ...rest
}) {
  const v = Math.max(0, Math.min(100, value));
  if (kind === "circle") {
    const C = 2 * Math.PI * 15;
    return /*#__PURE__*/React.createElement("svg", _extends({
      role: "progressbar",
      "aria-valuenow": v,
      width: size,
      height: size,
      viewBox: "0 0 36 36",
      style: style
    }, rest), /*#__PURE__*/React.createElement("circle", {
      cx: "18",
      cy: "18",
      r: "15",
      fill: "none",
      stroke: "var(--fila-hover)",
      strokeWidth: "4"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "18",
      cy: "18",
      r: "15",
      fill: "none",
      stroke: "var(--color-acento)",
      strokeWidth: "4",
      strokeLinecap: "round",
      strokeDasharray: `${v / 100 * C} ${C}`,
      transform: "rotate(-90 18 18)"
    }));
  }
  return /*#__PURE__*/React.createElement("span", _extends({
    role: "progressbar",
    "aria-valuenow": v,
    style: {
      display: "block",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), label || showValue ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "flex",
      justifyContent: "space-between",
      fontSize: 11,
      color: "var(--texto-secundario)",
      marginBottom: 6
    }
  }, /*#__PURE__*/React.createElement("span", null, label), showValue ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: "var(--font-mono)",
      fontWeight: 600
    }
  }, v, "%") : null) : null, /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      height: 6,
      borderRadius: 999,
      background: "var(--fila-hover)",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      height: "100%",
      width: v + "%",
      borderRadius: 999,
      background: "var(--color-acento)"
    }
  })));
}
Object.assign(__ds_scope, { Progress });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Progress.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Skeleton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
let __pdSkeletonCss = false;

/**
 * Bloque skeleton de carga. El shimmer se inyecta una sola vez y
 * respeta "Reducir movimiento" (queda estático).
 */
function Skeleton({
  width = "100%",
  height = 10,
  circle = false,
  radius = 6,
  style,
  ...rest
}) {
  if (!__pdSkeletonCss && typeof document !== "undefined") {
    __pdSkeletonCss = true;
    if (!document.getElementById("pd-skeleton-css")) {
      const s = document.createElement("style");
      s.id = "pd-skeleton-css";
      s.textContent = "@media (prefers-reduced-motion: no-preference){.pd-sk::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent 25%,color-mix(in srgb, var(--fondo-contenido) 85%, transparent) 50%,transparent 75%);background-size:300% 100%;animation:pd-shimmer 1.6s ease infinite}@keyframes pd-shimmer{from{background-position:150% 0}to{background-position:-150% 0}}}";
      document.head.appendChild(s);
    }
  }
  return /*#__PURE__*/React.createElement("span", _extends({
    className: "pd-sk",
    "aria-hidden": "true",
    style: {
      display: "block",
      width: circle ? height : width,
      height,
      borderRadius: circle ? 999 : radius,
      background: "var(--fila-hover)",
      position: "relative",
      overflow: "hidden",
      ...style
    }
  }, rest));
}
Object.assign(__ds_scope, { Skeleton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Skeleton.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Spinner.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Spinner de operación puntual (guardar, handshake, reembolso). 16px por defecto.
 * Animación SMIL (sin CSS global); respeta "Reducir movimiento" si la app lo gestiona.
 */
function Spinner({
  size = 16,
  color = "var(--texto-secundario)",
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("svg", _extends({
    width: size,
    height: size,
    viewBox: "0 0 14 14",
    style: style
  }, rest), /*#__PURE__*/React.createElement("path", {
    d: "M7 1.5 A5.5 5.5 0 1 1 1.5 7",
    fill: "none",
    style: {
      stroke: color
    },
    strokeWidth: "2",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("animateTransform", {
    attributeName: "transform",
    type: "rotate",
    from: "0 7 7",
    to: "360 7 7",
    dur: "0.8s",
    repeatCount: "indefinite"
  })));
}
Object.assign(__ds_scope, { Spinner });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Spinner.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Toast.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Toast: confirmación efímera, invertida sobre el fondo. `dot` añade un punto
 * (p. ej. sync) del color indicado. La posición/animación las pone la app.
 */
function Toast({
  children,
  dot = false,
  dotColor = "var(--color-sync)",
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 8,
      background: "var(--texto-primario)",
      color: "var(--fondo-contenido)",
      fontFamily: "var(--font-ui)",
      fontSize: 12.5,
      fontWeight: 500,
      padding: "8px 16px",
      borderRadius: 999,
      boxShadow: "var(--sombra-popover)",
      ...style
    }
  }, rest), dot ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: 7,
      height: 7,
      borderRadius: 999,
      background: dotColor
    }
  }) : null, children);
}
Object.assign(__ds_scope, { Toast });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Toast.jsx", error: String((e && e.message) || e) }); }

// components/forms/Checkbox.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Casilla de verificación. `indeterminate` para selecciones parciales.
 * Relleno marcado en acento; la marca va SIEMPRE en --sobre-acento.
 */
function Checkbox({
  checked = false,
  indeterminate = false,
  disabled = false,
  label = null,
  onChange,
  style,
  ...rest
}) {
  const on = checked || indeterminate;
  return /*#__PURE__*/React.createElement("label", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 8,
      fontFamily: "var(--font-ui)",
      fontSize: 13,
      color: "var(--texto-primario)",
      opacity: disabled ? 0.45 : 1,
      cursor: disabled ? "default" : "pointer",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    role: "checkbox",
    "aria-checked": indeterminate ? "mixed" : checked,
    tabIndex: disabled ? -1 : 0,
    onClick: disabled ? undefined : onChange,
    style: {
      width: 16,
      height: 16,
      boxSizing: "border-box",
      borderRadius: 4,
      flex: "0 0 auto",
      display: "grid",
      placeItems: "center",
      background: on ? "var(--color-acento)" : "var(--fondo-contenido)",
      border: on ? "1.5px solid var(--color-acento)" : "1.5px solid var(--separador)",
      color: "var(--sobre-acento)",
      transition: "background var(--dur-hover)"
    }
  }, indeterminate ? /*#__PURE__*/React.createElement("svg", {
    width: "10",
    height: "10",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "3.4",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M5 12h14"
  })) : checked ? /*#__PURE__*/React.createElement("svg", {
    width: "10",
    height: "10",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "3.4",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M4 12l6 6L20 6"
  })) : null), label);
}
Object.assign(__ds_scope, { Checkbox });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Checkbox.jsx", error: String((e && e.message) || e) }); }

// components/forms/DatePicker.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Calendario de selección de fecha (presentacional). Semana empieza en lunes.
 * `firstDay` = desplazamiento del día 1 (0 = lunes).
 */
function DatePicker({
  month = "",
  days = 31,
  firstDay = 0,
  selected = 1,
  onSelect,
  style,
  ...rest
}) {
  const heads = ["M", "T", "W", "T", "F", "S", "S"];
  const cells = [];
  for (let i = 0; i < firstDay; i++) cells.push(null);
  for (let d = 1; d <= days; d++) cells.push(d);
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      background: "var(--fondo-contenido)",
      border: "1px solid var(--separador)",
      borderRadius: "var(--radio-popover)",
      boxShadow: "var(--sombra-popover)",
      padding: 10,
      width: 200,
      boxSizing: "border-box",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      fontSize: 12,
      fontWeight: 600,
      padding: "0 2px 8px",
      color: "var(--texto-primario)"
    }
  }, /*#__PURE__*/React.createElement("span", null, month), /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--texto-terciario)"
    }
  }, "\u2039 \u203A")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(7,1fr)",
      gap: 2,
      fontFamily: "var(--font-mono)",
      fontSize: 10.5,
      textAlign: "center"
    }
  }, heads.map((h, i) => /*#__PURE__*/React.createElement("span", {
    key: "h" + i,
    style: {
      color: "var(--texto-terciario)",
      padding: "2px 0"
    }
  }, h)), cells.map((d, i) => d === null ? /*#__PURE__*/React.createElement("span", {
    key: i
  }) : /*#__PURE__*/React.createElement("span", {
    key: i,
    onClick: onSelect ? () => onSelect(d) : undefined,
    style: {
      padding: "4px 0",
      borderRadius: 6,
      cursor: "pointer",
      background: d === selected ? "var(--color-acento)" : "transparent",
      color: d === selected ? "var(--sobre-acento)" : "var(--texto-primario)",
      fontWeight: d === selected ? 700 : 400
    }
  }, d))));
}
Object.assign(__ds_scope, { DatePicker });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/DatePicker.jsx", error: String((e && e.message) || e) }); }

// components/forms/FileUpload.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Zona de subida de archivos (dropzone). El realce de arrastre lo
 * gestiona la app cambiando `active`.
 */
function FileUpload({
  label = "Choose a file",
  hint = null,
  active = false,
  onBrowse,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "button",
    tabIndex: 0,
    onClick: onBrowse,
    style: {
      border: `1.5px dashed ${active ? "var(--color-acento)" : "var(--separador)"}`,
      borderRadius: "var(--radio-card)",
      background: active ? "var(--tinte-acento)" : "var(--fondo-contenido)",
      padding: 16,
      textAlign: "center",
      color: "var(--texto-secundario)",
      fontSize: 12,
      fontFamily: "var(--font-ui)",
      cursor: "pointer",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("b", {
    style: {
      color: "var(--color-acento)",
      fontWeight: 600
    }
  }, label), " or drop it here", hint ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 10.5,
      color: "var(--texto-terciario)"
    }
  }, hint)) : null);
}
Object.assign(__ds_scope, { FileUpload });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/FileUpload.jsx", error: String((e && e.message) || e) }); }

// components/forms/Radio.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Botón de opción (grupo de selección única). Marcado = aro y punto en acento.
 */
function Radio({
  checked = false,
  disabled = false,
  label = null,
  onChange,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("label", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 8,
      fontFamily: "var(--font-ui)",
      fontSize: 13,
      color: "var(--texto-primario)",
      opacity: disabled ? 0.45 : 1,
      cursor: disabled ? "default" : "pointer",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    role: "radio",
    "aria-checked": checked,
    tabIndex: disabled ? -1 : 0,
    onClick: disabled ? undefined : onChange,
    style: {
      width: 16,
      height: 16,
      boxSizing: "border-box",
      borderRadius: 999,
      flex: "0 0 auto",
      display: "grid",
      placeItems: "center",
      background: "var(--fondo-contenido)",
      border: checked ? "1.5px solid var(--color-acento)" : "1.5px solid var(--separador)",
      transition: "border-color var(--dur-hover)"
    }
  }, checked ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: 8,
      height: 8,
      borderRadius: 999,
      background: "var(--color-acento)"
    }
  }) : null), label);
}
Object.assign(__ds_scope, { Radio });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Radio.jsx", error: String((e && e.message) || e) }); }

// components/forms/SearchField.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Campo de búsqueda con lupa. `mono` para buscar por SKU/nº de pedido.
 * No desactiva el foco del navegador: el anillo debe verse siempre.
 */
function SearchField({
  value,
  placeholder = "Search",
  mono = false,
  onChange,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      position: "relative",
      display: "inline-flex",
      width: "100%",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("svg", {
    width: "14",
    height: "14",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "var(--texto-terciario)",
    strokeWidth: "1.8",
    strokeLinecap: "round",
    style: {
      position: "absolute",
      left: 10,
      top: "50%",
      transform: "translateY(-50%)",
      pointerEvents: "none"
    }
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "11",
    cy: "11",
    r: "7"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M20 20l-3.5-3.5"
  })), /*#__PURE__*/React.createElement("input", {
    type: "search",
    value: value,
    placeholder: placeholder,
    onChange: onChange,
    style: {
      width: "100%",
      boxSizing: "border-box",
      fontFamily: mono ? "var(--font-mono)" : "var(--font-ui)",
      fontSize: 12.5,
      color: "var(--texto-primario)",
      background: "var(--fondo-contenido)",
      border: "1px solid var(--separador)",
      borderRadius: "var(--radio-control)",
      padding: "7px 10px 7px 30px"
    }
  }));
}
Object.assign(__ds_scope, { SearchField });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/SearchField.jsx", error: String((e && e.message) || e) }); }

// components/forms/Select.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Desplegable con etiqueta y pista. `options` admite strings u objetos {value,label}.
 */
function Select({
  label = null,
  options = [],
  value,
  onChange,
  hint = null,
  invalid = false,
  style,
  id,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: "block",
      fontFamily: "var(--font-ui)"
    }
  }, label ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      fontSize: 12,
      fontWeight: 600,
      marginBottom: 5,
      color: "var(--texto-primario)"
    }
  }, label) : null, /*#__PURE__*/React.createElement("select", _extends({
    id: id,
    value: value,
    onChange: onChange,
    style: {
      width: "100%",
      boxSizing: "border-box",
      fontFamily: "var(--font-ui)",
      fontSize: 13,
      color: "var(--texto-primario)",
      background: "var(--fondo-contenido)",
      border: `1px solid ${invalid ? "var(--color-peligro)" : "var(--separador)"}`,
      borderRadius: "var(--radio-control)",
      padding: "7px 10px",
      ...style
    }
  }, rest), options.map((o, i) => {
    const v = o && typeof o === "object" ? o.value : o;
    const l = o && typeof o === "object" ? o.label : o;
    return /*#__PURE__*/React.createElement("option", {
      key: i,
      value: v
    }, l);
  })), hint ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      fontSize: 11,
      marginTop: 5,
      color: invalid ? "var(--color-peligro)" : "var(--texto-terciario)"
    }
  }, hint) : null);
}
Object.assign(__ds_scope, { Select });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Select.jsx", error: String((e && e.message) || e) }); }

// components/forms/Slider.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Deslizador (0–100 por defecto). Presentacional/controlado: pinta pista,
 * relleno en acento y tirador; el valor se muestra en mono si showValue.
 */
function Slider({
  value = 50,
  min = 0,
  max = 100,
  showValue = false,
  onChange,
  style,
  ...rest
}) {
  const pct = (value - min) / (max - min) * 100;
  return /*#__PURE__*/React.createElement("span", _extends({
    role: "slider",
    "aria-valuenow": value,
    "aria-valuemin": min,
    "aria-valuemax": max,
    tabIndex: 0,
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 12,
      width: "100%",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    style: {
      position: "relative",
      height: 4,
      borderRadius: 999,
      background: "var(--separador)",
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      left: 0,
      top: 0,
      bottom: 0,
      width: pct + "%",
      borderRadius: 999,
      background: "var(--color-acento)"
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      left: pct + "%",
      top: "50%",
      transform: "translate(-50%,-50%)",
      width: 16,
      height: 16,
      borderRadius: 999,
      background: "var(--fondo-contenido)",
      border: "1.5px solid var(--color-acento)",
      boxShadow: "var(--sombra-card)",
      boxSizing: "border-box"
    }
  })), showValue ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 12,
      fontWeight: 600,
      color: "var(--texto-primario)"
    }
  }, value, "%") : null);
}
Object.assign(__ds_scope, { Slider });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Slider.jsx", error: String((e && e.message) || e) }); }

// components/forms/Stepper.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Stepper numérico (cantidades de línea de pedido). Valor en mono.
 */
function Stepper({
  value = 0,
  min = 0,
  max = 99,
  disabled = false,
  onChange,
  style,
  ...rest
}) {
  const btn = {
    display: "grid",
    placeItems: "center",
    width: 28,
    border: "none",
    background: "transparent",
    color: "var(--texto-secundario)",
    fontSize: 14,
    cursor: disabled ? "default" : "pointer",
    fontFamily: "var(--font-ui)"
  };
  const set = v => {
    if (!disabled && onChange && v >= min && v <= max) onChange(v);
  };
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      display: "inline-flex",
      alignItems: "stretch",
      border: "1px solid var(--separador)",
      borderRadius: "var(--radio-control)",
      overflow: "hidden",
      background: "var(--fondo-contenido)",
      opacity: disabled ? 0.45 : 1,
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("button", {
    "aria-label": "Decrease",
    onClick: () => set(value - 1),
    style: btn
  }, "\u2212"), /*#__PURE__*/React.createElement("b", {
    style: {
      display: "grid",
      placeItems: "center",
      minWidth: 40,
      fontFamily: "var(--font-mono)",
      fontSize: 12.5,
      fontWeight: 600,
      color: "var(--texto-primario)",
      borderLeft: "1px solid var(--separador)",
      borderRight: "1px solid var(--separador)",
      padding: "6px 0"
    }
  }, value), /*#__PURE__*/React.createElement("button", {
    "aria-label": "Increase",
    onClick: () => set(value + 1),
    style: btn
  }, "+"));
}
Object.assign(__ds_scope, { Stepper });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Stepper.jsx", error: String((e && e.message) || e) }); }

// components/forms/Switch.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Conmutador on/off. Pista del acento cuando está activo. Pensado para
 * ajustes; en móvil cumple el objetivo táctil con el área de la etiqueta.
 */
function Switch({
  checked = false,
  onChange,
  disabled = false,
  label = null,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("label", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 10,
      fontFamily: "var(--font-ui)",
      fontSize: 13,
      color: "var(--texto-primario)",
      cursor: disabled ? "default" : "pointer",
      opacity: disabled ? 0.6 : 1,
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    role: "switch",
    "aria-checked": checked,
    onClick: () => {
      if (!disabled && onChange) onChange(!checked);
    },
    style: {
      position: "relative",
      width: 38,
      height: 22,
      borderRadius: 999,
      flex: "0 0 auto",
      background: checked ? "var(--color-acento)" : "var(--separador)",
      transition: "background var(--dur-hover) var(--easing-estandar)"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      top: 2,
      left: checked ? 18 : 2,
      width: 18,
      height: 18,
      borderRadius: 999,
      background: "#FFFFFF",
      boxShadow: "0 1px 2px rgba(0,0,0,.3)",
      transition: "left var(--dur-hover) var(--easing-estandar)"
    }
  })), label);
}
Object.assign(__ds_scope, { Switch });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Switch.jsx", error: String((e && e.message) || e) }); }

// components/forms/Textarea.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Área de texto multilínea con etiqueta y pista. `invalid` en peligro.
 */
function Textarea({
  label = null,
  hint = null,
  invalid = false,
  rows = 3,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: "block",
      fontFamily: "var(--font-ui)"
    }
  }, label ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      fontSize: 12,
      fontWeight: 600,
      marginBottom: 5,
      color: "var(--texto-primario)"
    }
  }, label) : null, /*#__PURE__*/React.createElement("textarea", _extends({
    rows: rows,
    style: {
      width: "100%",
      boxSizing: "border-box",
      resize: "vertical",
      fontFamily: "var(--font-ui)",
      fontSize: 13,
      color: "var(--texto-primario)",
      background: "var(--fondo-contenido)",
      border: `1px solid ${invalid ? "var(--color-peligro)" : "var(--separador)"}`,
      borderRadius: "var(--radio-control)",
      padding: "7px 10px",
      ...style
    }
  }, rest)), hint ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      fontSize: 11,
      marginTop: 4,
      color: invalid ? "var(--color-peligro)" : "var(--texto-terciario)"
    }
  }, hint) : null);
}
Object.assign(__ds_scope, { Textarea });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Textarea.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Breadcrumb.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Miga de pan. `items` admite {label, mono} — el último es la página actual.
 */
function Breadcrumb({
  items = [],
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("nav", _extends({
    style: {
      display: "flex",
      alignItems: "center",
      gap: 8,
      fontSize: 12.5,
      color: "var(--texto-secundario)",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), items.map((it, i) => {
    const last = i === items.length - 1;
    const label = it && typeof it === "object" ? it.label : it;
    const mono = it && typeof it === "object" && it.mono;
    return /*#__PURE__*/React.createElement(React.Fragment, {
      key: i
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: mono ? "var(--font-mono)" : "var(--font-ui)",
        color: last ? "var(--texto-primario)" : "var(--texto-secundario)",
        fontWeight: last ? 600 : 400
      }
    }, label), !last ? /*#__PURE__*/React.createElement("span", {
      style: {
        color: "var(--texto-terciario)",
        fontStyle: "normal"
      }
    }, "/") : null);
  }));
}
Object.assign(__ds_scope, { Breadcrumb });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Breadcrumb.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Menu.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Menú desplegable (popover). `items` admite '-' como separador y
 * objetos {label, icon, shortcut, danger, selected, onSelect}.
 * El atajo va SIEMPRE en monoespaciada.
 */
function Menu({
  items = [],
  width = 240,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "menu",
    style: {
      width,
      background: "var(--fondo-elevado)",
      border: "1px solid var(--separador)",
      borderRadius: "var(--radio-popover)",
      boxShadow: "var(--sombra-popover)",
      padding: 5,
      fontFamily: "var(--font-ui)",
      fontSize: 12.5,
      boxSizing: "border-box",
      ...style
    }
  }, rest), items.map((it, i) => {
    if (it === "-") return /*#__PURE__*/React.createElement("div", {
      key: i,
      style: {
        height: 1,
        background: "var(--separador)",
        margin: "5px 4px"
      }
    });
    const sel = !!it.selected;
    return /*#__PURE__*/React.createElement("div", {
      key: i,
      role: "menuitem",
      tabIndex: 0,
      onClick: it.onSelect,
      style: {
        display: "flex",
        alignItems: "center",
        gap: 10,
        padding: "7px 9px",
        borderRadius: 7,
        cursor: "pointer",
        background: sel ? "var(--fila-seleccion)" : "transparent",
        color: it.danger ? "var(--color-peligro)" : sel ? "var(--color-acento)" : "var(--texto-primario)",
        fontWeight: sel ? 600 : 400
      }
    }, it.icon || null, it.label, it.shortcut ? /*#__PURE__*/React.createElement("span", {
      style: {
        marginLeft: "auto",
        fontFamily: "var(--font-mono)",
        fontSize: 11,
        color: "var(--texto-terciario)"
      }
    }, it.shortcut) : null);
  }));
}
Object.assign(__ds_scope, { Menu });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Menu.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Pagination.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Paginación. Página activa con relleno en acento; números en mono.
 */
function Pagination({
  page = 1,
  pages = 1,
  onChange,
  style,
  ...rest
}) {
  const cell = {
    minWidth: 28,
    height: 28,
    display: "grid",
    placeItems: "center",
    borderRadius: "var(--radio-control)",
    fontFamily: "var(--font-mono)",
    fontSize: 12,
    color: "var(--texto-secundario)",
    border: "1px solid transparent",
    cursor: "pointer",
    padding: "0 4px",
    boxSizing: "border-box"
  };
  const nums = [];
  if (pages <= 7) {
    for (let i = 1; i <= pages; i++) nums.push(i);
  } else if (page <= 4) nums.push(1, 2, 3, 4, 5, "…", pages);else if (page >= pages - 3) nums.push(1, "…", pages - 4, pages - 3, pages - 2, pages - 1, pages);else nums.push(1, "…", page - 1, page, page + 1, "…", pages);
  return /*#__PURE__*/React.createElement("nav", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 4,
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    onClick: onChange && page > 1 ? () => onChange(page - 1) : undefined,
    style: {
      ...cell,
      borderColor: "var(--separador)",
      background: "var(--fondo-contenido)"
    }
  }, "\u2039"), nums.map((n, i) => n === "…" ? /*#__PURE__*/React.createElement("span", {
    key: i,
    style: {
      ...cell,
      color: "var(--texto-terciario)",
      cursor: "default"
    }
  }, "\u2026") : /*#__PURE__*/React.createElement("span", {
    key: i,
    onClick: onChange ? () => onChange(n) : undefined,
    style: {
      ...cell,
      background: n === page ? "var(--color-acento)" : "transparent",
      color: n === page ? "var(--sobre-acento)" : "var(--texto-secundario)",
      fontWeight: n === page ? 700 : 400
    }
  }, n)), /*#__PURE__*/React.createElement("span", {
    onClick: onChange && page < pages ? () => onChange(page + 1) : undefined,
    style: {
      ...cell,
      borderColor: "var(--separador)",
      background: "var(--fondo-contenido)"
    }
  }, "\u203A"));
}
Object.assign(__ds_scope, { Pagination });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Pagination.jsx", error: String((e && e.message) || e) }); }

// components/navigation/SegmentedControl.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Control segmentado (lozenge deslizable en Apple). Para 2–4 vistas
 * del mismo contenido; NO es navegación de secciones (eso es Tabs).
 */
function SegmentedControl({
  items = [],
  active = 0,
  onChange,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({
    role: "tablist",
    style: {
      display: "inline-flex",
      background: "var(--fila-hover)",
      borderRadius: 999,
      padding: 3,
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), items.map((label, i) => {
    const on = i === active;
    return /*#__PURE__*/React.createElement("span", {
      key: i,
      role: "tab",
      "aria-selected": on,
      tabIndex: 0,
      onClick: onChange ? () => onChange(i) : undefined,
      style: {
        padding: "5px 14px",
        borderRadius: 999,
        fontSize: 12,
        cursor: "pointer",
        background: on ? "var(--fondo-contenido)" : "transparent",
        color: on ? "var(--texto-primario)" : "var(--texto-secundario)",
        fontWeight: on ? 600 : 400,
        boxShadow: on ? "var(--sombra-card)" : "none",
        transition: "background var(--dur-hover)"
      }
    }, label);
  }));
}
Object.assign(__ds_scope, { SegmentedControl });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/SegmentedControl.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Tabs.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Pestañas de sección con subrayado en acento. `items` admite strings
 * u objetos {id,label}; `active` es el id/índice activo.
 */
function Tabs({
  items = [],
  active = 0,
  onChange,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "tablist",
    style: {
      display: "flex",
      gap: 22,
      borderBottom: "1px solid var(--separador)",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), items.map((it, i) => {
    const id = it && typeof it === "object" ? it.id : i;
    const label = it && typeof it === "object" ? it.label : it;
    const on = id === active || i === active && !(it && typeof it === "object");
    return /*#__PURE__*/React.createElement("span", {
      key: i,
      role: "tab",
      "aria-selected": on,
      tabIndex: 0,
      onClick: onChange ? () => onChange(id) : undefined,
      style: {
        position: "relative",
        padding: "8px 2px 9px",
        fontSize: 13,
        cursor: "pointer",
        color: on ? "var(--texto-primario)" : "var(--texto-secundario)",
        fontWeight: on ? 600 : 400
      }
    }, label, on ? /*#__PURE__*/React.createElement("span", {
      style: {
        position: "absolute",
        left: 0,
        right: 0,
        bottom: -1,
        height: 2,
        borderRadius: 999,
        background: "var(--color-acento)"
      }
    }) : null);
  }));
}
Object.assign(__ds_scope, { Tabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Tabs.jsx", error: String((e && e.message) || e) }); }

// components/overlays/Banner.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Banner/alerta en línea. SIEMPRE con acción (nunca un aviso mudo).
 * Tinte + texto del tono semántico.
 */
function Banner({
  tone = "info",
  action = null,
  onAction,
  icon = null,
  children,
  style,
  ...rest
}) {
  const TONO = {
    info: "info",
    exito: "exito",
    aviso: "aviso",
    peligro: "peligro"
  }[tone] || "info";
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "status",
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      padding: "9px 12px",
      borderRadius: "var(--radio-card)",
      fontFamily: "var(--font-ui)",
      fontSize: 12,
      background: `var(--tinte-${TONO})`,
      color: `var(--color-${TONO})`,
      ...style
    }
  }, rest), icon, /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, children), action ? /*#__PURE__*/React.createElement("span", {
    role: "button",
    tabIndex: 0,
    onClick: onAction,
    style: {
      fontWeight: 600,
      whiteSpace: "nowrap",
      cursor: "pointer"
    }
  }, action) : null);
}
Object.assign(__ds_scope, { Banner });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/overlays/Banner.jsx", error: String((e && e.message) || e) }); }

// components/overlays/Dialog.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Diálogo de confirmación (solo el panel; el velo/posición los pone la app).
 * UNA acción primaria tintada por diálogo; `destructive` la pinta en peligro.
 */
function Dialog({
  title,
  children,
  confirmLabel = "OK",
  cancelLabel = "Cancel",
  destructive = false,
  onConfirm,
  onCancel,
  style,
  ...rest
}) {
  const btn = {
    display: "inline-flex",
    alignItems: "center",
    justifyContent: "center",
    fontFamily: "var(--font-ui)",
    fontWeight: 500,
    fontSize: 12.5,
    padding: "7px 14px",
    borderRadius: "var(--radio-control)",
    border: "1px solid transparent",
    cursor: "pointer",
    whiteSpace: "nowrap"
  };
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "alertdialog",
    "aria-label": typeof title === "string" ? title : undefined,
    style: {
      background: "var(--fondo-elevado)",
      borderRadius: "var(--radio-popover)",
      boxShadow: "var(--sombra-hoja)",
      padding: 18,
      width: 300,
      boxSizing: "border-box",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 14,
      fontWeight: 700,
      color: "var(--texto-primario)",
      marginBottom: 6
    }
  }, title), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      lineHeight: 1.5,
      color: "var(--texto-secundario)",
      marginBottom: 14
    }
  }, children), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 8,
      justifyContent: "flex-end"
    }
  }, /*#__PURE__*/React.createElement("button", {
    onClick: onCancel,
    style: {
      ...btn,
      background: "var(--fondo-contenido)",
      color: "var(--texto-primario)",
      borderColor: "var(--separador)"
    }
  }, cancelLabel), /*#__PURE__*/React.createElement("button", {
    onClick: onConfirm,
    style: {
      ...btn,
      background: destructive ? "var(--color-peligro)" : "var(--color-acento)",
      color: "var(--sobre-acento)"
    }
  }, confirmLabel)));
}
Object.assign(__ds_scope, { Dialog });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/overlays/Dialog.jsx", error: String((e && e.message) || e) }); }

// components/overlays/Popover.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Popover informativo (panel; la posición la pone la app).
 */
function Popover({
  title = null,
  width = 220,
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "tooltip",
    style: {
      background: "var(--fondo-elevado)",
      border: "1px solid var(--separador)",
      borderRadius: "var(--radio-popover)",
      boxShadow: "var(--sombra-popover)",
      padding: 12,
      width,
      boxSizing: "border-box",
      fontFamily: "var(--font-ui)",
      fontSize: 12,
      lineHeight: 1.5,
      color: "var(--texto-secundario)",
      ...style
    }
  }, rest), title ? /*#__PURE__*/React.createElement("b", {
    style: {
      color: "var(--texto-primario)",
      fontWeight: 600
    }
  }, title, /*#__PURE__*/React.createElement("br", null)) : null, children);
}
Object.assign(__ds_scope, { Popover });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/overlays/Popover.jsx", error: String((e && e.message) || e) }); }

// components/overlays/Sheet.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Hoja / bottom sheet (panel; el velo y la posición los pone la app).
 * Acciones tipo lista con separadores; destructiva en peligro.
 */
function Sheet({
  items = [],
  grabber = true,
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "dialog",
    style: {
      background: "var(--fondo-elevado)",
      borderRadius: "14px 14px 0 0",
      boxShadow: "var(--sombra-hoja)",
      padding: "10px 16px 14px",
      boxSizing: "border-box",
      fontFamily: "var(--font-ui)",
      ...style
    }
  }, rest), grabber ? /*#__PURE__*/React.createElement("div", {
    style: {
      width: 36,
      height: 4,
      borderRadius: 999,
      background: "var(--separador)",
      margin: "0 auto 10px"
    }
  }) : null, items.map((it, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    role: "button",
    tabIndex: 0,
    onClick: it.onSelect,
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      padding: "11px 2px",
      fontSize: 13.5,
      cursor: "pointer",
      borderBottom: i < items.length - 1 ? "1px solid var(--separador)" : "none",
      color: it.danger ? "var(--color-peligro)" : "var(--texto-primario)"
    }
  }, it.icon || null, it.label)), children);
}
Object.assign(__ds_scope, { Sheet });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/overlays/Sheet.jsx", error: String((e && e.message) || e) }); }

// components/overlays/Tooltip.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Tooltip: etiqueta corta invertida (como Toast). Atajos en mono dentro
 * del texto si hace falta.
 */
function Tooltip({
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({
    role: "tooltip",
    style: {
      display: "inline-block",
      background: "var(--texto-primario)",
      color: "var(--fondo-contenido)",
      fontSize: 11,
      fontWeight: 500,
      padding: "5px 9px",
      borderRadius: 6,
      boxShadow: "var(--sombra-popover)",
      fontFamily: "var(--font-ui)",
      whiteSpace: "nowrap",
      ...style
    }
  }, rest), children);
}
Object.assign(__ds_scope, { Tooltip });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/overlays/Tooltip.jsx", error: String((e && e.message) || e) }); }

__ds_ns.AppIcon = __ds_scope.AppIcon;

__ds_ns.Logo = __ds_scope.Logo;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.Chip = __ds_scope.Chip;

__ds_ns.ListRow = __ds_scope.ListRow;

__ds_ns.StatCard = __ds_scope.StatCard;

__ds_ns.Table = __ds_scope.Table;

__ds_ns.EmptyState = __ds_scope.EmptyState;

__ds_ns.Progress = __ds_scope.Progress;

__ds_ns.Skeleton = __ds_scope.Skeleton;

__ds_ns.Spinner = __ds_scope.Spinner;

__ds_ns.Toast = __ds_scope.Toast;

__ds_ns.Checkbox = __ds_scope.Checkbox;

__ds_ns.DatePicker = __ds_scope.DatePicker;

__ds_ns.FileUpload = __ds_scope.FileUpload;

__ds_ns.Radio = __ds_scope.Radio;

__ds_ns.SearchField = __ds_scope.SearchField;

__ds_ns.Select = __ds_scope.Select;

__ds_ns.Slider = __ds_scope.Slider;

__ds_ns.Stepper = __ds_scope.Stepper;

__ds_ns.Switch = __ds_scope.Switch;

__ds_ns.Textarea = __ds_scope.Textarea;

__ds_ns.Breadcrumb = __ds_scope.Breadcrumb;

__ds_ns.Menu = __ds_scope.Menu;

__ds_ns.Pagination = __ds_scope.Pagination;

__ds_ns.SegmentedControl = __ds_scope.SegmentedControl;

__ds_ns.Tabs = __ds_scope.Tabs;

__ds_ns.Banner = __ds_scope.Banner;

__ds_ns.Dialog = __ds_scope.Dialog;

__ds_ns.Popover = __ds_scope.Popover;

__ds_ns.Sheet = __ds_scope.Sheet;

__ds_ns.Tooltip = __ds_scope.Tooltip;

})();
