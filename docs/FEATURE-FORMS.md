# Feature: Sistema de Formularios con Logica Condicional

## Objetivo

Crear un sistema de formularios completo, comparable en funcionalidad a Gravity Forms, pero con un enfoque AI-first: TODO se debe poder crear, modificar, consultar y gestionar via MCP y Chat API. El sistema debe incluir:

1. **Creacion de formularios** con multiples tipos de campo y validaciones.
2. **Logica condicional** para mostrar/ocultar campos segun las respuestas del usuario.
3. **Almacenamiento de respuestas** (entries) en la base de datos con consulta completa.
4. **Notificaciones por email** al recibir respuestas.
5. **Insercion sencilla en paginas** mediante shortcode o bloque.
6. **Diseno moderno** basado en las CSS variables de Klytos, totalmente personalizable.
7. **Formularios multi-paso** (wizard/stepper).
8. **Subida de archivos** desde formularios.
9. **Anti-spam** integrado (honeypot + rate limiting).

---

## 1. Nueva coleccion: `forms` (definicion de formularios)

### 1.1 Estructura de un formulario

Cada formulario es un documento en la coleccion `forms`:

```json
{
    "id": "contact-form",
    "title": "Formulario de contacto",
    "description": "Formulario principal de la web",
    "status": "active",
    "fields": [
        {
            "id": "field_name",
            "type": "text",
            "label": "Nombre completo",
            "placeholder": "Tu nombre",
            "required": true,
            "validation": {
                "min_length": 2,
                "max_length": 100
            },
            "css_class": "",
            "default_value": "",
            "step": 1,
            "order": 1
        },
        {
            "id": "field_email",
            "type": "email",
            "label": "Email",
            "placeholder": "tu@email.com",
            "required": true,
            "validation": {},
            "css_class": "",
            "default_value": "",
            "step": 1,
            "order": 2
        },
        {
            "id": "field_subject",
            "type": "select",
            "label": "Asunto",
            "required": true,
            "options": [
                {"value": "", "label": "Selecciona un asunto"},
                {"value": "info", "label": "Informacion general"},
                {"value": "support", "label": "Soporte tecnico"},
                {"value": "billing", "label": "Facturacion"}
            ],
            "css_class": "",
            "default_value": "",
            "step": 1,
            "order": 3
        },
        {
            "id": "field_order_id",
            "type": "text",
            "label": "Numero de pedido",
            "placeholder": "Ej: ORD-12345",
            "required": true,
            "validation": {
                "pattern": "^ORD-[0-9]+$",
                "pattern_message": "El formato debe ser ORD-seguido de numeros"
            },
            "css_class": "",
            "default_value": "",
            "step": 1,
            "order": 4,
            "conditional": {
                "action": "show",
                "logic": "all",
                "rules": [
                    {
                        "field_id": "field_subject",
                        "operator": "is",
                        "value": "billing"
                    }
                ]
            }
        },
        {
            "id": "field_message",
            "type": "textarea",
            "label": "Mensaje",
            "placeholder": "Escribe tu mensaje...",
            "required": true,
            "validation": {
                "min_length": 10,
                "max_length": 5000
            },
            "css_class": "",
            "default_value": "",
            "step": 1,
            "order": 5
        }
    ],
    "settings": {
        "submit_label": "Enviar mensaje",
        "success_message": "Gracias por tu mensaje. Te responderemos pronto.",
        "success_action": "message",
        "success_redirect": "",
        "enable_ajax": true,
        "css_class": "",
        "layout": "stacked",
        "steps": [
            {"step": 1, "title": "Formulario de contacto"}
        ]
    },
    "notifications": [
        {
            "id": "admin_notification",
            "name": "Notificacion al administrador",
            "enabled": true,
            "to": "{{site_email}}",
            "reply_to": "{{field_email}}",
            "subject": "Nuevo mensaje de contacto: {{field_subject}}",
            "body": "Has recibido un nuevo mensaje de contacto.\n\nNombre: {{field_name}}\nEmail: {{field_email}}\nAsunto: {{field_subject}}\nMensaje:\n{{field_message}}",
            "format": "text",
            "conditional": null
        },
        {
            "id": "user_confirmation",
            "name": "Confirmacion al usuario",
            "enabled": true,
            "to": "{{field_email}}",
            "reply_to": "{{site_email}}",
            "subject": "Hemos recibido tu mensaje",
            "body": "Hola {{field_name}},\n\nHemos recibido tu mensaje correctamente. Te responderemos lo antes posible.\n\nGracias.",
            "format": "text",
            "conditional": null
        }
    ],
    "anti_spam": {
        "honeypot": true,
        "rate_limit": 3,
        "rate_limit_window": 60
    },
    "created_by": "admin",
    "created_at": "2026-04-01T10:00:00+00:00",
    "updated_at": "2026-04-01T10:00:00+00:00"
}
```

### 1.2 Tipos de campo soportados

El sistema debe soportar los siguientes tipos de campo desde el inicio:

| Tipo | Descripcion | Propiedades extra |
|------|-------------|-------------------|
| `text` | Texto corto | `min_length`, `max_length`, `pattern` |
| `email` | Email con validacion | - |
| `url` | URL con validacion | - |
| `phone` | Telefono | `pattern` |
| `number` | Numerico | `min`, `max`, `step` |
| `textarea` | Texto largo | `min_length`, `max_length`, `rows` |
| `select` | Desplegable | `options[]`, `multiple` |
| `radio` | Opciones radio | `options[]` |
| `checkbox` | Checkbox individual | - |
| `checkbox_group` | Grupo de checkboxes | `options[]`, `min_selected`, `max_selected` |
| `date` | Selector de fecha | `min_date`, `max_date`, `format` |
| `time` | Selector de hora | `format_24h` |
| `file` | Subida de archivo | `allowed_types[]`, `max_size`, `max_files` |
| `hidden` | Campo oculto | `default_value` |
| `html` | Bloque HTML libre | `content` (HTML puro, no es campo de entrada) |
| `section` | Separador/titulo | `content` (solo visual, no es campo de entrada) |
| `consent` | Checkbox de consentimiento | `consent_text`, `required` siempre true |
| `password` | Contrasena | `min_length`, `confirm` (mostrar segundo campo) |
| `range` | Slider numerico | `min`, `max`, `step` |

### 1.3 Generacion del ID de campo

Los IDs de campo se generan automaticamente al crear un campo: `field_` + sanitized label o `field_` + hash corto si no hay label. El ID debe ser unico dentro del formulario. La IA puede proponer IDs semanticos al crear campos.

---

## 2. Logica condicional

### 2.1 Estructura de reglas condicionales

Cada campo puede tener un objeto `conditional` que determina cuando se muestra u oculta:

```json
{
    "conditional": {
        "action": "show",
        "logic": "all",
        "rules": [
            {
                "field_id": "field_subject",
                "operator": "is",
                "value": "billing"
            },
            {
                "field_id": "field_name",
                "operator": "is_not_empty",
                "value": null
            }
        ]
    }
}
```

**Campos del condicional:**
- `action`: `"show"` (mostrar si se cumple) o `"hide"` (ocultar si se cumple).
- `logic`: `"all"` (AND: todas las reglas deben cumplirse) o `"any"` (OR: al menos una).
- `rules[]`: lista de reglas individuales.

### 2.2 Operadores disponibles

| Operador | Descripcion | Tipos de campo compatibles |
|----------|-------------|---------------------------|
| `is` | Igual a valor | Todos |
| `is_not` | Distinto de valor | Todos |
| `contains` | Contiene texto | `text`, `textarea`, `email`, `url` |
| `not_contains` | No contiene texto | `text`, `textarea`, `email`, `url` |
| `starts_with` | Empieza por | `text`, `textarea`, `email`, `url` |
| `ends_with` | Termina por | `text`, `textarea`, `email`, `url` |
| `greater_than` | Mayor que | `number`, `range` |
| `less_than` | Menor que | `number`, `range` |
| `is_empty` | Esta vacio | Todos |
| `is_not_empty` | No esta vacio | Todos |
| `is_checked` | Esta marcado | `checkbox`, `consent` |
| `is_not_checked` | No esta marcado | `checkbox`, `consent` |
| `in` | Valor esta en lista | `select`, `radio`, `checkbox_group` |
| `not_in` | Valor no esta en lista | `select`, `radio`, `checkbox_group` |

### 2.3 Logica condicional en notificaciones

Las notificaciones tambien pueden tener condicionales. Solo se envian si se cumple la condicion:

```json
{
    "id": "billing_notification",
    "name": "Notificacion especifica de facturacion",
    "enabled": true,
    "to": "billing@empresa.com",
    "subject": "Consulta de facturacion: {{field_order_id}}",
    "body": "...",
    "conditional": {
        "logic": "all",
        "rules": [
            {
                "field_id": "field_subject",
                "operator": "is",
                "value": "billing"
            }
        ]
    }
}
```

### 2.4 Motor de evaluacion de condicionales (backend)

Crear la clase `FormConditionalEngine` en `core/form-conditional-engine.php`:

```php
class FormConditionalEngine
{
    /**
     * Evaluar si un condicional se cumple dado un conjunto de datos del formulario.
     *
     * @param  array|null $conditional Objeto condicional del campo/notificacion.
     * @param  array      $formData   Datos enviados (field_id => value).
     * @return bool       true si el campo debe mostrarse / la notificacion debe enviarse.
     */
    public function evaluate(?array $conditional, array $formData): bool
    {
        if ($conditional === null) {
            return true; // Sin condicional = siempre visible/activo
        }

        $action = $conditional['action'] ?? 'show';
        $logic  = $conditional['logic'] ?? 'all';
        $rules  = $conditional['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        $results = [];
        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($rule, $formData);
        }

        $passed = ($logic === 'all')
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);

        // Si action es "show", devolver $passed directamente.
        // Si action es "hide", invertir.
        return ($action === 'show') ? $passed : !$passed;
    }

    /**
     * Evaluar una regla individual.
     */
    private function evaluateRule(array $rule, array $formData): bool
    {
        $fieldValue = $formData[$rule['field_id']] ?? null;
        $ruleValue  = $rule['value'] ?? null;
        $operator   = $rule['operator'] ?? 'is';

        return match ($operator) {
            'is'             => (string)$fieldValue === (string)$ruleValue,
            'is_not'         => (string)$fieldValue !== (string)$ruleValue,
            'contains'       => str_contains((string)$fieldValue, (string)$ruleValue),
            'not_contains'   => !str_contains((string)$fieldValue, (string)$ruleValue),
            'starts_with'    => str_starts_with((string)$fieldValue, (string)$ruleValue),
            'ends_with'      => str_ends_with((string)$fieldValue, (string)$ruleValue),
            'greater_than'   => (float)$fieldValue > (float)$ruleValue,
            'less_than'      => (float)$fieldValue < (float)$ruleValue,
            'is_empty'       => $fieldValue === null || $fieldValue === '' || $fieldValue === [],
            'is_not_empty'   => $fieldValue !== null && $fieldValue !== '' && $fieldValue !== [],
            'is_checked'     => (bool)$fieldValue === true || $fieldValue === '1' || $fieldValue === 'on',
            'is_not_checked' => (bool)$fieldValue === false || $fieldValue === '0' || $fieldValue === '' || $fieldValue === null,
            'in'             => is_array($ruleValue) && in_array((string)$fieldValue, $ruleValue, true),
            'not_in'         => is_array($ruleValue) && !in_array((string)$fieldValue, $ruleValue, true),
            default          => true,
        };
    }
}
```

### 2.5 Motor de condicionales (frontend - JavaScript)

Crear `public/js/klytos-forms.js` con el motor de condicionales en el cliente para reactividad inmediata:

```javascript
class KlytosFormEngine {
    constructor(formElement, formConfig) {
        this.form = formElement;
        this.config = formConfig;
        this.fields = formConfig.fields || [];
        this.currentStep = 1;
        this.totalSteps = formConfig.settings?.steps?.length || 1;

        this.init();
    }

    init() {
        this.bindEvents();
        this.evaluateAllConditionals();
        if (this.totalSteps > 1) {
            this.initStepper();
        }
    }

    bindEvents() {
        // Escuchar cambios en todos los campos para reevaluar condicionales
        this.form.querySelectorAll('input, select, textarea').forEach(el => {
            const eventType = ['checkbox', 'radio', 'select-one', 'select-multiple']
                .includes(el.type) ? 'change' : 'input';
            el.addEventListener(eventType, () => this.evaluateAllConditionals());
        });
    }

    getFormData() {
        const data = {};
        this.fields.forEach(field => {
            if (field.type === 'html' || field.type === 'section') return;

            const el = this.form.querySelector(`[name="${field.id}"]`);
            if (!el) return;

            if (field.type === 'checkbox' || field.type === 'consent') {
                data[field.id] = el.checked;
            } else if (field.type === 'checkbox_group') {
                const checked = this.form.querySelectorAll(`[name="${field.id}[]"]:checked`);
                data[field.id] = Array.from(checked).map(c => c.value);
            } else if (field.type === 'file') {
                data[field.id] = el.files.length > 0 ? el.files : null;
            } else {
                data[field.id] = el.value;
            }
        });
        return data;
    }

    evaluateAllConditionals() {
        const data = this.getFormData();

        this.fields.forEach(field => {
            if (!field.conditional) return;

            const wrapper = this.form.querySelector(`[data-field-id="${field.id}"]`);
            if (!wrapper) return;

            const visible = this.evaluateConditional(field.conditional, data);
            wrapper.style.display = visible ? '' : 'none';
            wrapper.dataset.conditionalVisible = visible ? 'true' : 'false';

            // Desactivar validacion de campos ocultos
            const inputs = wrapper.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (visible) {
                    if (input.dataset.wasRequired === 'true') {
                        input.required = true;
                    }
                } else {
                    input.dataset.wasRequired = input.required ? 'true' : 'false';
                    input.required = false;
                }
            });
        });
    }

    evaluateConditional(conditional, data) {
        const { action = 'show', logic = 'all', rules = [] } = conditional;
        if (rules.length === 0) return true;

        const results = rules.map(rule => this.evaluateRule(rule, data));
        const passed = logic === 'all'
            ? results.every(r => r)
            : results.some(r => r);

        return action === 'show' ? passed : !passed;
    }

    evaluateRule(rule, data) {
        const fieldValue = data[rule.field_id];
        const ruleValue = rule.value;
        const operator = rule.operator || 'is';

        const strVal = String(fieldValue ?? '');
        const strRule = String(ruleValue ?? '');

        switch (operator) {
            case 'is':             return strVal === strRule;
            case 'is_not':         return strVal !== strRule;
            case 'contains':       return strVal.includes(strRule);
            case 'not_contains':   return !strVal.includes(strRule);
            case 'starts_with':    return strVal.startsWith(strRule);
            case 'ends_with':      return strVal.endsWith(strRule);
            case 'greater_than':   return parseFloat(fieldValue) > parseFloat(ruleValue);
            case 'less_than':      return parseFloat(fieldValue) < parseFloat(ruleValue);
            case 'is_empty':       return fieldValue === null || fieldValue === '' || (Array.isArray(fieldValue) && fieldValue.length === 0);
            case 'is_not_empty':   return fieldValue !== null && fieldValue !== '' && !(Array.isArray(fieldValue) && fieldValue.length === 0);
            case 'is_checked':     return fieldValue === true || fieldValue === 'on' || fieldValue === '1';
            case 'is_not_checked': return fieldValue === false || fieldValue === '' || fieldValue === null || fieldValue === '0';
            case 'in':             return Array.isArray(ruleValue) && ruleValue.includes(strVal);
            case 'not_in':         return Array.isArray(ruleValue) && !ruleValue.includes(strVal);
            default:               return true;
        }
    }

    // --- Stepper para formularios multi-paso ---

    initStepper() {
        this.showStep(1);
        this.renderStepIndicator();
    }

    showStep(step) {
        this.currentStep = step;
        this.form.querySelectorAll('[data-step]').forEach(el => {
            el.style.display = parseInt(el.dataset.step) === step ? '' : 'none';
        });
        this.updateStepButtons();
        this.renderStepIndicator();
    }

    updateStepButtons() {
        const prevBtn = this.form.querySelector('[data-action="prev-step"]');
        const nextBtn = this.form.querySelector('[data-action="next-step"]');
        const submitBtn = this.form.querySelector('[data-action="submit"]');

        if (prevBtn) prevBtn.style.display = this.currentStep > 1 ? '' : 'none';
        if (nextBtn) nextBtn.style.display = this.currentStep < this.totalSteps ? '' : 'none';
        if (submitBtn) submitBtn.style.display = this.currentStep === this.totalSteps ? '' : 'none';
    }

    nextStep() {
        if (!this.validateCurrentStep()) return;
        if (this.currentStep < this.totalSteps) {
            this.showStep(this.currentStep + 1);
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.showStep(this.currentStep - 1);
        }
    }

    validateCurrentStep() {
        const stepFields = this.form.querySelectorAll(
            `[data-step="${this.currentStep}"] input, [data-step="${this.currentStep}"] select, [data-step="${this.currentStep}"] textarea`
        );
        let valid = true;
        stepFields.forEach(field => {
            // Solo validar campos visibles (no ocultos por condicional)
            const wrapper = field.closest('[data-field-id]');
            if (wrapper && wrapper.dataset.conditionalVisible === 'false') return;
            if (!field.checkValidity()) {
                field.reportValidity();
                valid = false;
            }
        });
        return valid;
    }

    renderStepIndicator() {
        const indicator = this.form.querySelector('.klytos-form-steps');
        if (!indicator) return;

        const steps = this.config.settings?.steps || [];
        indicator.innerHTML = steps.map((s, i) => {
            const num = i + 1;
            const state = num < this.currentStep ? 'completed'
                        : num === this.currentStep ? 'active'
                        : 'pending';
            return `<div class="klytos-form-step klytos-form-step--${state}">
                <span class="klytos-form-step__number">${num}</span>
                <span class="klytos-form-step__title">${s.title}</span>
            </div>`;
        }).join('<div class="klytos-form-step__connector"></div>');
    }
}
```

**NOTA:** Este archivo se carga automaticamente cuando una pagina contiene un formulario de Klytos. No se carga globalmente.

---

## 3. Nueva coleccion: `form-entries` (respuestas)

### 3.1 Estructura de una entrada

```json
{
    "id": "entry_a1b2c3d4",
    "form_id": "contact-form",
    "data": {
        "field_name": "Maria Garcia",
        "field_email": "maria@ejemplo.com",
        "field_subject": "support",
        "field_message": "Tengo un problema con mi cuenta..."
    },
    "files": [],
    "metadata": {
        "ip": "192.168.1.1",
        "user_agent": "Mozilla/5.0...",
        "referrer": "https://ejemplo.com/contacto",
        "page_url": "/contacto",
        "submitted_at": "2026-04-01T14:30:00+00:00",
        "locale": "es-ES"
    },
    "status": "unread",
    "notes": "",
    "is_spam": false,
    "notifications_sent": ["admin_notification", "user_confirmation"],
    "created_at": "2026-04-01T14:30:00+00:00"
}
```

**Estados de una entrada:**
- `unread`: nueva, no leida.
- `read`: leida por un admin.
- `starred`: marcada como importante.
- `trash`: en la papelera.

### 3.2 Archivos subidos

Cuando un formulario tiene campos `file`, los archivos se suben al directorio `public/assets/form-uploads/YYYY/mm/` y se registran tanto en la entrada como en la coleccion `assets`:

```json
{
    "files": [
        {
            "field_id": "field_cv",
            "original_name": "curriculum-maria.pdf",
            "stored_path": "assets/form-uploads/2026/04/curriculum-maria_a1b2c3d4.pdf",
            "mime_type": "application/pdf",
            "size": 524288,
            "asset_id": "f9e8d7c6"
        }
    ]
}
```

---

## 4. `FormManager` (`core/form-manager.php`)

### 4.1 Dependencias

```php
class FormManager
{
    private StorageInterface $storage;
    private FormConditionalEngine $conditionalEngine;
    private ?AssetManager $assetManager;

    private const FORMS_COLLECTION   = 'forms';
    private const ENTRIES_COLLECTION  = 'form-entries';

    public function __construct(
        StorageInterface $storage,
        FormConditionalEngine $conditionalEngine,
        ?AssetManager $assetManager = null
    ) {
        $this->storage            = $storage;
        $this->conditionalEngine  = $conditionalEngine;
        $this->assetManager       = $assetManager;
    }
}
```

### 4.2 CRUD de formularios

```php
/**
 * Crear un formulario.
 */
public function createForm(array $data): array
{
    $id = $data['id'] ?? Helpers::sanitizeSlug($data['title'] ?? 'form-' . Helpers::generateShortId());

    // Verificar unicidad
    if ($this->storage->exists(self::FORMS_COLLECTION, $id)) {
        throw new \RuntimeException("Form '{$id}' already exists.");
    }

    // Asegurar IDs unicos en campos
    $data['fields'] = $this->normalizeFieldIds($data['fields'] ?? []);

    $form = [
        'id'            => $id,
        'title'         => $data['title'] ?? 'Sin titulo',
        'description'   => $data['description'] ?? '',
        'status'        => $data['status'] ?? 'active',
        'fields'        => $data['fields'],
        'settings'      => array_merge($this->defaultSettings(), $data['settings'] ?? []),
        'notifications' => $data['notifications'] ?? [],
        'anti_spam'     => array_merge($this->defaultAntiSpam(), $data['anti_spam'] ?? []),
        'created_by'    => klytos_current_user()['id'] ?? 'system',
        'created_at'    => Helpers::now(),
        'updated_at'    => Helpers::now(),
    ];

    $this->storage->write(self::FORMS_COLLECTION, $id, $form);

    klytos_do_action('form.after_create', $form);

    return $form;
}

/**
 * Obtener un formulario por ID.
 */
public function getForm(string $id): ?array
{
    if (!$this->storage->exists(self::FORMS_COLLECTION, $id)) {
        return null;
    }
    return $this->storage->read(self::FORMS_COLLECTION, $id);
}

/**
 * Listar todos los formularios.
 */
public function listForms(?string $status = null): array
{
    $all = $this->storage->list(self::FORMS_COLLECTION);

    if ($status !== null) {
        $all = array_filter($all, fn($f) => ($f['status'] ?? '') === $status);
    }

    return array_values($all);
}

/**
 * Actualizar un formulario.
 */
public function updateForm(string $id, array $data): array
{
    $form = $this->getForm($id);
    if (!$form) {
        throw new \RuntimeException("Form '{$id}' not found.");
    }

    // Merge selectivo: solo actualizar los campos proporcionados
    if (isset($data['title']))         $form['title']         = $data['title'];
    if (isset($data['description']))   $form['description']   = $data['description'];
    if (isset($data['status']))        $form['status']        = $data['status'];
    if (isset($data['fields']))        $form['fields']        = $this->normalizeFieldIds($data['fields']);
    if (isset($data['settings']))      $form['settings']      = array_merge($form['settings'], $data['settings']);
    if (isset($data['notifications'])) $form['notifications'] = $data['notifications'];
    if (isset($data['anti_spam']))     $form['anti_spam']     = array_merge($form['anti_spam'], $data['anti_spam']);

    $form['updated_at'] = Helpers::now();

    $this->storage->write(self::FORMS_COLLECTION, $id, $form);

    klytos_do_action('form.after_update', $form);

    return $form;
}

/**
 * Eliminar un formulario (y opcionalmente sus entradas).
 */
public function deleteForm(string $id, bool $deleteEntries = false): bool
{
    if (!$this->storage->exists(self::FORMS_COLLECTION, $id)) {
        return false;
    }

    klytos_do_action('form.before_delete', $id);

    if ($deleteEntries) {
        $this->deleteEntriesByForm($id);
    }

    $deleted = $this->storage->delete(self::FORMS_COLLECTION, $id);

    if ($deleted) {
        klytos_do_action('form.after_delete', $id);
    }

    return $deleted;
}

/**
 * Duplicar un formulario.
 */
public function duplicateForm(string $id, ?string $newTitle = null): array
{
    $form = $this->getForm($id);
    if (!$form) {
        throw new \RuntimeException("Form '{$id}' not found.");
    }

    $newId    = $id . '-copy-' . Helpers::generateShortId();
    $newTitle = $newTitle ?? $form['title'] . ' (copia)';

    $form['id']         = $newId;
    $form['title']      = $newTitle;
    $form['created_at'] = Helpers::now();
    $form['updated_at'] = Helpers::now();

    $this->storage->write(self::FORMS_COLLECTION, $newId, $form);

    return $form;
}
```

### 4.3 Gestion de campos

```php
/**
 * Anadir un campo al formulario.
 */
public function addField(string $formId, array $fieldData, ?int $position = null): array
{
    $form = $this->getForm($formId);
    if (!$form) {
        throw new \RuntimeException("Form '{$formId}' not found.");
    }

    // Generar ID si no se proporciona
    if (empty($fieldData['id'])) {
        $fieldData['id'] = 'field_' . Helpers::generateShortId();
    }

    // Verificar unicidad del ID
    foreach ($form['fields'] as $existing) {
        if ($existing['id'] === $fieldData['id']) {
            throw new \RuntimeException("Field ID '{$fieldData['id']}' already exists in form.");
        }
    }

    // Asignar orden
    if ($position !== null) {
        $fieldData['order'] = $position;
        // Reordenar los demas
        foreach ($form['fields'] as &$f) {
            if ($f['order'] >= $position) {
                $f['order']++;
            }
        }
        unset($f);
    } else {
        $maxOrder = max(array_column($form['fields'], 'order') ?: [0]);
        $fieldData['order'] = $maxOrder + 1;
    }

    // Valores por defecto
    $fieldData = array_merge([
        'type'          => 'text',
        'label'         => '',
        'placeholder'   => '',
        'required'      => false,
        'validation'    => [],
        'css_class'     => '',
        'default_value' => '',
        'step'          => 1,
        'conditional'   => null,
    ], $fieldData);

    $form['fields'][] = $fieldData;

    // Ordenar por 'order'
    usort($form['fields'], fn($a, $b) => ($a['order'] ?? 0) - ($b['order'] ?? 0));

    $form['updated_at'] = Helpers::now();
    $this->storage->write(self::FORMS_COLLECTION, $formId, $form);

    return $fieldData;
}

/**
 * Actualizar un campo existente.
 */
public function updateField(string $formId, string $fieldId, array $fieldData): array
{
    $form = $this->getForm($formId);
    if (!$form) {
        throw new \RuntimeException("Form '{$formId}' not found.");
    }

    $found = false;
    foreach ($form['fields'] as &$field) {
        if ($field['id'] === $fieldId) {
            $field = array_merge($field, $fieldData);
            $field['id'] = $fieldId; // No permitir cambiar el ID via merge
            $found = true;
            break;
        }
    }
    unset($field);

    if (!$found) {
        throw new \RuntimeException("Field '{$fieldId}' not found in form '{$formId}'.");
    }

    $form['updated_at'] = Helpers::now();
    $this->storage->write(self::FORMS_COLLECTION, $formId, $form);

    return $form;
}

/**
 * Eliminar un campo del formulario.
 */
public function removeField(string $formId, string $fieldId): bool
{
    $form = $this->getForm($formId);
    if (!$form) {
        throw new \RuntimeException("Form '{$formId}' not found.");
    }

    $originalCount = count($form['fields']);
    $form['fields'] = array_values(
        array_filter($form['fields'], fn($f) => $f['id'] !== $fieldId)
    );

    if (count($form['fields']) === $originalCount) {
        return false; // No se encontro el campo
    }

    // Limpiar condicionales que referencien este campo
    foreach ($form['fields'] as &$field) {
        if (isset($field['conditional']['rules'])) {
            $field['conditional']['rules'] = array_values(
                array_filter($field['conditional']['rules'], fn($r) => $r['field_id'] !== $fieldId)
            );
            if (empty($field['conditional']['rules'])) {
                $field['conditional'] = null;
            }
        }
    }
    unset($field);

    $form['updated_at'] = Helpers::now();
    $this->storage->write(self::FORMS_COLLECTION, $formId, $form);

    return true;
}

/**
 * Reordenar campos.
 */
public function reorderFields(string $formId, array $fieldIdsInOrder): array
{
    $form = $this->getForm($formId);
    if (!$form) {
        throw new \RuntimeException("Form '{$formId}' not found.");
    }

    $indexed = [];
    foreach ($form['fields'] as $field) {
        $indexed[$field['id']] = $field;
    }

    $reordered = [];
    $order = 1;
    foreach ($fieldIdsInOrder as $fieldId) {
        if (isset($indexed[$fieldId])) {
            $indexed[$fieldId]['order'] = $order++;
            $reordered[] = $indexed[$fieldId];
            unset($indexed[$fieldId]);
        }
    }
    // Anadir campos no mencionados al final
    foreach ($indexed as $field) {
        $field['order'] = $order++;
        $reordered[] = $field;
    }

    $form['fields'] = $reordered;
    $form['updated_at'] = Helpers::now();
    $this->storage->write(self::FORMS_COLLECTION, $formId, $form);

    return $form;
}
```

### 4.4 Procesamiento de envios (submissions)

```php
/**
 * Procesar el envio de un formulario.
 *
 * @param  string $formId  ID del formulario.
 * @param  array  $rawData Datos enviados ($_POST).
 * @param  array  $files   Archivos enviados ($_FILES).
 * @param  array  $meta    Metadatos (ip, user_agent, referrer, etc.).
 * @return array  La entrada creada o array con errores.
 */
public function submitForm(string $formId, array $rawData, array $files = [], array $meta = []): array
{
    $form = $this->getForm($formId);
    if (!$form || $form['status'] !== 'active') {
        return ['success' => false, 'errors' => ['form' => 'Formulario no disponible.']];
    }

    // 1. Anti-spam: honeypot
    if (($form['anti_spam']['honeypot'] ?? true) && !empty($rawData['_klytos_hp'])) {
        // Bot detectado: simular exito pero no guardar
        return ['success' => true, 'message' => $form['settings']['success_message'] ?? 'Gracias.'];
    }

    // 2. Anti-spam: rate limiting
    if ($this->isRateLimited($formId, $meta['ip'] ?? '')) {
        return ['success' => false, 'errors' => ['form' => 'Demasiados envios. Intentalo de nuevo mas tarde.']];
    }

    // 3. Determinar campos visibles (evaluar condicionales)
    $visibleFieldIds = [];
    foreach ($form['fields'] as $field) {
        if ($field['type'] === 'html' || $field['type'] === 'section') continue;
        if ($this->conditionalEngine->evaluate($field['conditional'] ?? null, $rawData)) {
            $visibleFieldIds[] = $field['id'];
        }
    }

    // 4. Validar solo campos visibles y requeridos
    $errors = $this->validateSubmission($form, $rawData, $visibleFieldIds);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // 5. Sanitizar datos (solo campos visibles)
    $cleanData = [];
    foreach ($form['fields'] as $field) {
        if (!in_array($field['id'], $visibleFieldIds)) continue;
        $cleanData[$field['id']] = $this->sanitizeFieldValue($field, $rawData[$field['id']] ?? null);
    }

    // 6. Procesar archivos
    $fileRecords = [];
    if ($this->assetManager) {
        foreach ($form['fields'] as $field) {
            if ($field['type'] !== 'file') continue;
            if (!in_array($field['id'], $visibleFieldIds)) continue;
            if (!isset($files[$field['id']])) continue;

            $uploaded = $this->processFileUpload($field, $files[$field['id']]);
            if ($uploaded) {
                $fileRecords = array_merge($fileRecords, $uploaded);
            }
        }
    }

    // 7. Crear entrada
    $entryId = 'entry_' . Helpers::generateShortId();
    $entry = [
        'id'                  => $entryId,
        'form_id'             => $formId,
        'data'                => $cleanData,
        'files'               => $fileRecords,
        'metadata'            => [
            'ip'           => $meta['ip'] ?? '',
            'user_agent'   => $meta['user_agent'] ?? '',
            'referrer'     => $meta['referrer'] ?? '',
            'page_url'     => $meta['page_url'] ?? '',
            'submitted_at' => Helpers::now(),
            'locale'       => $meta['locale'] ?? '',
        ],
        'status'              => 'unread',
        'notes'               => '',
        'is_spam'             => false,
        'notifications_sent'  => [],
        'created_at'          => Helpers::now(),
    ];

    $this->storage->write(self::ENTRIES_COLLECTION, $entryId, $entry);

    klytos_do_action('form.entry_created', $entry, $form);

    // 8. Enviar notificaciones
    $sentNotifications = $this->sendNotifications($form, $entry);
    $entry['notifications_sent'] = $sentNotifications;
    $this->storage->write(self::ENTRIES_COLLECTION, $entryId, $entry);

    // 9. Respuesta
    $response = [
        'success'  => true,
        'entry_id' => $entryId,
        'message'  => $form['settings']['success_message'] ?? 'Formulario enviado correctamente.',
    ];

    if (($form['settings']['success_action'] ?? 'message') === 'redirect') {
        $response['redirect'] = $form['settings']['success_redirect'] ?? '';
    }

    return $response;
}

/**
 * Validar los datos enviados contra la definicion del formulario.
 */
private function validateSubmission(array $form, array $data, array $visibleFieldIds): array
{
    $errors = [];

    foreach ($form['fields'] as $field) {
        if (!in_array($field['id'], $visibleFieldIds)) continue;
        if ($field['type'] === 'html' || $field['type'] === 'section') continue;

        $value = $data[$field['id']] ?? null;

        // Required
        if (($field['required'] ?? false) && ($value === null || $value === '' || $value === [])) {
            $errors[$field['id']] = "El campo \"{$field['label']}\" es obligatorio.";
            continue;
        }

        // Saltar validaciones adicionales si esta vacio y no es requerido
        if ($value === null || $value === '') continue;

        $validation = $field['validation'] ?? [];

        // Min length
        if (isset($validation['min_length']) && mb_strlen($value) < $validation['min_length']) {
            $errors[$field['id']] = "Minimo {$validation['min_length']} caracteres.";
        }

        // Max length
        if (isset($validation['max_length']) && mb_strlen($value) > $validation['max_length']) {
            $errors[$field['id']] = "Maximo {$validation['max_length']} caracteres.";
        }

        // Pattern
        if (isset($validation['pattern']) && !preg_match('/' . $validation['pattern'] . '/', $value)) {
            $errors[$field['id']] = $validation['pattern_message'] ?? 'Formato no valido.';
        }

        // Email
        if ($field['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field['id']] = 'Email no valido.';
        }

        // URL
        if ($field['type'] === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $errors[$field['id']] = 'URL no valida.';
        }

        // Number range
        if ($field['type'] === 'number' || $field['type'] === 'range') {
            if (isset($validation['min']) && (float)$value < (float)$validation['min']) {
                $errors[$field['id']] = "El valor minimo es {$validation['min']}.";
            }
            if (isset($validation['max']) && (float)$value > (float)$validation['max']) {
                $errors[$field['id']] = "El valor maximo es {$validation['max']}.";
            }
        }

        // Checkbox group selection limits
        if ($field['type'] === 'checkbox_group' && is_array($value)) {
            if (isset($validation['min_selected']) && count($value) < $validation['min_selected']) {
                $errors[$field['id']] = "Selecciona al menos {$validation['min_selected']} opciones.";
            }
            if (isset($validation['max_selected']) && count($value) > $validation['max_selected']) {
                $errors[$field['id']] = "Selecciona como maximo {$validation['max_selected']} opciones.";
            }
        }
    }

    return $errors;
}

/**
 * Rate limiting basado en IP y formulario.
 */
private function isRateLimited(string $formId, string $ip): bool
{
    if (empty($ip)) return false;

    $form = $this->getForm($formId);
    $limit  = $form['anti_spam']['rate_limit'] ?? 3;
    $window = $form['anti_spam']['rate_limit_window'] ?? 60;

    if ($limit <= 0) return false;

    $entries = $this->storage->list(self::ENTRIES_COLLECTION);
    $cutoff  = date('c', time() - $window);
    $count   = 0;

    foreach ($entries as $entry) {
        if (($entry['form_id'] ?? '') === $formId
            && ($entry['metadata']['ip'] ?? '') === $ip
            && ($entry['created_at'] ?? '') > $cutoff) {
            $count++;
        }
    }

    return $count >= $limit;
}
```

### 4.5 Gestion de entradas

```php
/**
 * Obtener una entrada por ID.
 */
public function getEntry(string $entryId): ?array
{
    if (!$this->storage->exists(self::ENTRIES_COLLECTION, $entryId)) {
        return null;
    }
    return $this->storage->read(self::ENTRIES_COLLECTION, $entryId);
}

/**
 * Listar entradas de un formulario con filtros.
 */
public function listEntries(string $formId, array $filters = []): array
{
    $all     = $this->storage->list(self::ENTRIES_COLLECTION);
    $entries = [];

    foreach ($all as $entry) {
        if (($entry['form_id'] ?? '') !== $formId) continue;

        // Filtro por estado
        if (isset($filters['status']) && ($entry['status'] ?? '') !== $filters['status']) continue;

        // Filtro por spam
        if (isset($filters['is_spam']) && ($entry['is_spam'] ?? false) !== $filters['is_spam']) continue;

        // Filtro por fecha desde
        if (isset($filters['date_from']) && ($entry['created_at'] ?? '') < $filters['date_from']) continue;

        // Filtro por fecha hasta
        if (isset($filters['date_to']) && ($entry['created_at'] ?? '') > $filters['date_to']) continue;

        // Busqueda en datos
        if (isset($filters['search'])) {
            $found = false;
            foreach (($entry['data'] ?? []) as $value) {
                if (is_string($value) && str_contains(mb_strtolower($value), mb_strtolower($filters['search']))) {
                    $found = true;
                    break;
                }
            }
            if (!$found) continue;
        }

        $entries[] = $entry;
    }

    // Ordenar por fecha descendente
    usort($entries, fn($a, $b) => ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''));

    // Paginacion
    $page    = max(1, (int)($filters['page'] ?? 1));
    $perPage = max(1, (int)($filters['per_page'] ?? 20));
    $total   = count($entries);
    $entries = array_slice($entries, ($page - 1) * $perPage, $perPage);

    return [
        'entries'  => $entries,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => (int)ceil($total / $perPage),
    ];
}

/**
 * Actualizar el estado de una entrada.
 */
public function updateEntryStatus(string $entryId, string $status): bool
{
    $entry = $this->getEntry($entryId);
    if (!$entry) return false;

    $entry['status'] = $status;
    $this->storage->write(self::ENTRIES_COLLECTION, $entryId, $entry);

    return true;
}

/**
 * Anadir una nota a una entrada.
 */
public function addEntryNote(string $entryId, string $note): bool
{
    $entry = $this->getEntry($entryId);
    if (!$entry) return false;

    $entry['notes'] = $note;
    $this->storage->write(self::ENTRIES_COLLECTION, $entryId, $entry);

    return true;
}

/**
 * Eliminar una entrada.
 */
public function deleteEntry(string $entryId): bool
{
    return $this->storage->delete(self::ENTRIES_COLLECTION, $entryId);
}

/**
 * Eliminar todas las entradas de un formulario.
 */
public function deleteEntriesByForm(string $formId): int
{
    $entries = $this->storage->list(self::ENTRIES_COLLECTION);
    $deleted = 0;

    foreach ($entries as $entry) {
        if (($entry['form_id'] ?? '') === $formId) {
            $this->storage->delete(self::ENTRIES_COLLECTION, $entry['id']);
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Exportar entradas a CSV.
 */
public function exportEntries(string $formId, string $format = 'csv'): string
{
    $form    = $this->getForm($formId);
    $result  = $this->listEntries($formId, ['per_page' => 99999]);
    $entries = $result['entries'];

    if ($format === 'csv') {
        $output = fopen('php://temp', 'r+');

        // Cabecera
        $headers = ['ID', 'Fecha'];
        foreach ($form['fields'] as $field) {
            if ($field['type'] === 'html' || $field['type'] === 'section') continue;
            $headers[] = $field['label'] ?: $field['id'];
        }
        $headers[] = 'Estado';
        fputcsv($output, $headers);

        // Filas
        foreach ($entries as $entry) {
            $row = [$entry['id'], $entry['metadata']['submitted_at'] ?? $entry['created_at']];
            foreach ($form['fields'] as $field) {
                if ($field['type'] === 'html' || $field['type'] === 'section') continue;
                $val = $entry['data'][$field['id']] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $row[] = $val;
            }
            $row[] = $entry['status'];
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    // JSON
    return json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Estadisticas de un formulario.
 */
public function getFormStats(string $formId): array
{
    $result = $this->listEntries($formId, ['per_page' => 99999]);
    $entries = $result['entries'];

    $stats = [
        'total'   => count($entries),
        'unread'  => 0,
        'read'    => 0,
        'starred' => 0,
        'trash'   => 0,
        'spam'    => 0,
        'today'   => 0,
        'week'    => 0,
        'month'   => 0,
    ];

    $now       = time();
    $todayStr  = date('Y-m-d');
    $weekAgo   = date('c', $now - 7 * 86400);
    $monthAgo  = date('c', $now - 30 * 86400);

    foreach ($entries as $entry) {
        $status = $entry['status'] ?? 'unread';
        if (isset($stats[$status])) $stats[$status]++;
        if ($entry['is_spam'] ?? false) $stats['spam']++;

        $created = $entry['created_at'] ?? '';
        if (str_starts_with($created, $todayStr)) $stats['today']++;
        if ($created > $weekAgo) $stats['week']++;
        if ($created > $monthAgo) $stats['month']++;
    }

    return $stats;
}
```

### 4.6 Notificaciones por email

```php
/**
 * Enviar notificaciones configuradas para un formulario.
 */
private function sendNotifications(array $form, array $entry): array
{
    $sent = [];

    foreach ($form['notifications'] ?? [] as $notification) {
        if (!($notification['enabled'] ?? true)) continue;

        // Evaluar condicional de la notificacion
        if (isset($notification['conditional'])) {
            if (!$this->conditionalEngine->evaluate($notification['conditional'], $entry['data'])) {
                continue;
            }
        }

        // Reemplazar merge tags
        $to      = $this->replaceMergeTags($notification['to'] ?? '', $entry, $form);
        $replyTo = $this->replaceMergeTags($notification['reply_to'] ?? '', $entry, $form);
        $subject = $this->replaceMergeTags($notification['subject'] ?? '', $entry, $form);
        $body    = $this->replaceMergeTags($notification['body'] ?? '', $entry, $form);

        if (empty($to)) continue;

        // Construir cabeceras
        $headers = "From: " . klytos_get_option('site_name', 'Klytos') . " <" . klytos_get_option('site_email', 'noreply@localhost') . ">\r\n";
        if (!empty($replyTo)) {
            $headers .= "Reply-To: {$replyTo}\r\n";
        }

        if (($notification['format'] ?? 'text') === 'html') {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        $success = mail($to, $subject, $body, $headers);

        if ($success) {
            $sent[] = $notification['id'] ?? $notification['name'] ?? 'unknown';
        }

        klytos_do_action('form.notification_sent', $notification, $entry, $success);
    }

    return $sent;
}

/**
 * Reemplazar merge tags {{field_id}} y variables del sistema.
 */
private function replaceMergeTags(string $template, array $entry, array $form): string
{
    // Variables del sistema
    $systemVars = [
        'site_name'     => klytos_get_option('site_name', 'Klytos'),
        'site_email'    => klytos_get_option('site_email', ''),
        'site_url'      => klytos_get_option('site_url', ''),
        'form_title'    => $form['title'] ?? '',
        'entry_id'      => $entry['id'] ?? '',
        'entry_date'    => $entry['metadata']['submitted_at'] ?? $entry['created_at'] ?? '',
        'entry_ip'      => $entry['metadata']['ip'] ?? '',
        'all_fields'    => $this->formatAllFields($entry['data'], $form),
    ];

    // Reemplazar variables del sistema
    foreach ($systemVars as $key => $value) {
        $template = str_replace('{{' . $key . '}}', (string)$value, $template);
    }

    // Reemplazar valores de campos
    foreach (($entry['data'] ?? []) as $fieldId => $value) {
        if (is_array($value)) $value = implode(', ', $value);
        $template = str_replace('{{' . $fieldId . '}}', (string)$value, $template);
    }

    return $template;
}

/**
 * Formatear todos los campos como texto para {{all_fields}}.
 */
private function formatAllFields(array $data, array $form): string
{
    $lines = [];
    foreach ($form['fields'] as $field) {
        if ($field['type'] === 'html' || $field['type'] === 'section') continue;
        $value = $data[$field['id']] ?? '';
        if (is_array($value)) $value = implode(', ', $value);
        $lines[] = ($field['label'] ?: $field['id']) . ': ' . $value;
    }
    return implode("\n", $lines);
}
```

### 4.7 Defaults

```php
private function defaultSettings(): array
{
    return [
        'submit_label'     => 'Enviar',
        'success_message'  => 'Formulario enviado correctamente.',
        'success_action'   => 'message',
        'success_redirect'  => '',
        'enable_ajax'      => true,
        'css_class'        => '',
        'layout'           => 'stacked',
        'steps'            => [
            ['step' => 1, 'title' => '']
        ],
    ];
}

private function defaultAntiSpam(): array
{
    return [
        'honeypot'            => true,
        'rate_limit'          => 3,
        'rate_limit_window'   => 60,
    ];
}

private function normalizeFieldIds(array $fields): array
{
    $usedIds = [];
    foreach ($fields as &$field) {
        if (empty($field['id'])) {
            $base = 'field_' . Helpers::sanitizeSlug($field['label'] ?? '');
            if (empty($base) || $base === 'field_') {
                $base = 'field_' . Helpers::generateShortId();
            }
            $field['id'] = $base;
        }
        // Asegurar unicidad
        while (in_array($field['id'], $usedIds)) {
            $field['id'] .= '_' . Helpers::generateShortId();
        }
        $usedIds[] = $field['id'];
    }
    unset($field);
    return $fields;
}
```

---

## 5. Renderizado de formularios (frontend)

### 5.1 Clase `FormRenderer` (`core/form-renderer.php`)

```php
class FormRenderer
{
    private FormManager $formManager;

    public function __construct(FormManager $formManager)
    {
        $this->formManager = $formManager;
    }

    /**
     * Renderizar un formulario como HTML.
     */
    public function render(string $formId, array $options = []): string
    {
        $form = $this->formManager->getForm($formId);
        if (!$form || $form['status'] !== 'active') {
            return '<!-- Klytos Form: formulario no disponible -->';
        }

        $settings   = $form['settings'] ?? [];
        $layout     = $settings['layout'] ?? 'stacked';
        $isMultiStep = count($settings['steps'] ?? []) > 1;
        $formClass  = 'klytos-form klytos-form--' . $layout;
        if (!empty($settings['css_class'])) {
            $formClass .= ' ' . $settings['css_class'];
        }

        $html = '';

        // CSS inline (se carga una sola vez por pagina)
        $html .= $this->renderCSS();

        // Apertura del form
        $html .= '<form class="' . $formClass . '" data-form-id="' . htmlspecialchars($formId) . '"';
        $html .= ' method="post" action="/api/forms/submit" enctype="multipart/form-data">';

        // Input oculto con form_id
        $html .= '<input type="hidden" name="_form_id" value="' . htmlspecialchars($formId) . '">';

        // CSRF token
        $html .= '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($this->getCsrfToken()) . '">';

        // Honeypot
        if ($form['anti_spam']['honeypot'] ?? true) {
            $html .= '<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">';
            $html .= '<input type="text" name="_klytos_hp" tabindex="-1" autocomplete="off">';
            $html .= '</div>';
        }

        // Step indicator (multi-paso)
        if ($isMultiStep) {
            $html .= '<div class="klytos-form-steps"></div>';
        }

        // Campos agrupados por step
        $fieldsByStep = [];
        foreach ($form['fields'] as $field) {
            $step = $field['step'] ?? 1;
            $fieldsByStep[$step][] = $field;
        }

        foreach ($fieldsByStep as $step => $fields) {
            $stepDisplay = ($isMultiStep && $step > 1) ? ' style="display:none"' : '';
            $html .= '<div class="klytos-form-step-content" data-step="' . $step . '"' . $stepDisplay . '>';

            foreach ($fields as $field) {
                $html .= $this->renderField($field);
            }

            $html .= '</div>';
        }

        // Botones
        $html .= '<div class="klytos-form__actions">';

        if ($isMultiStep) {
            $html .= '<button type="button" class="klytos-form__btn klytos-form__btn--secondary" data-action="prev-step" style="display:none">Anterior</button>';
            $html .= '<button type="button" class="klytos-form__btn klytos-form__btn--primary" data-action="next-step">Siguiente</button>';
        }

        $submitDisplay = $isMultiStep ? ' style="display:none"' : '';
        $html .= '<button type="submit" class="klytos-form__btn klytos-form__btn--primary" data-action="submit"' . $submitDisplay . '>';
        $html .= htmlspecialchars($settings['submit_label'] ?? 'Enviar');
        $html .= '</button>';

        $html .= '</div>';

        // Mensaje de exito (oculto)
        $html .= '<div class="klytos-form__success" style="display:none">';
        $html .= htmlspecialchars($settings['success_message'] ?? '');
        $html .= '</div>';

        // Mensaje de error general (oculto)
        $html .= '<div class="klytos-form__error" style="display:none"></div>';

        $html .= '</form>';

        // JS: configuracion del formulario + carga del engine
        $configJson = json_encode([
            'fields'   => $form['fields'],
            'settings' => $settings,
        ], JSON_UNESCAPED_UNICODE);

        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= '  var formEl = document.querySelector(\'[data-form-id="' . $formId . '"]\');';
        $html .= '  if (formEl && typeof KlytosFormEngine !== "undefined") {';
        $html .= '    new KlytosFormEngine(formEl, ' . $configJson . ');';
        $html .= '  }';
        $html .= '});';
        $html .= '</script>';

        return $html;
    }

    /**
     * Renderizar un campo individual.
     */
    private function renderField(array $field): string
    {
        $type     = $field['type'] ?? 'text';
        $id       = $field['id'] ?? '';
        $label    = $field['label'] ?? '';
        $required = $field['required'] ?? false;
        $cssClass = 'klytos-form__field klytos-form__field--' . $type;
        if (!empty($field['css_class'])) {
            $cssClass .= ' ' . $field['css_class'];
        }

        $conditionalAttr = '';
        if (!empty($field['conditional'])) {
            $conditionalAttr = ' data-conditional=\'' . htmlspecialchars(json_encode($field['conditional']), ENT_QUOTES) . '\'';
        }

        $html = '<div class="' . $cssClass . '" data-field-id="' . htmlspecialchars($id) . '"' . $conditionalAttr . '>';

        // Label (excepto para html, section, hidden)
        if (!in_array($type, ['html', 'section', 'hidden'])) {
            $html .= '<label class="klytos-form__label" for="' . htmlspecialchars($id) . '">';
            $html .= htmlspecialchars($label);
            if ($required) {
                $html .= ' <span class="klytos-form__required">*</span>';
            }
            $html .= '</label>';
        }

        // Renderizado segun tipo
        $html .= match ($type) {
            'text', 'email', 'url', 'phone', 'password' => $this->renderInputField($field),
            'number', 'range'     => $this->renderNumberField($field),
            'textarea'            => $this->renderTextarea($field),
            'select'              => $this->renderSelect($field),
            'radio'               => $this->renderRadioGroup($field),
            'checkbox'            => $this->renderCheckbox($field),
            'checkbox_group'      => $this->renderCheckboxGroup($field),
            'consent'             => $this->renderConsent($field),
            'date', 'time'        => $this->renderDateTimeField($field),
            'file'                => $this->renderFileField($field),
            'hidden'              => $this->renderHiddenField($field),
            'html'                => $field['content'] ?? '',
            'section'             => '<div class="klytos-form__section-title">' . htmlspecialchars($field['content'] ?? $label) . '</div>',
            default               => $this->renderInputField($field),
        };

        // Contenedor de error del campo
        $html .= '<div class="klytos-form__field-error" data-error-for="' . htmlspecialchars($id) . '"></div>';

        $html .= '</div>';

        return $html;
    }

    // --- Metodos de renderizado por tipo ---

    private function renderInputField(array $field): string
    {
        $type = match ($field['type']) {
            'phone' => 'tel',
            default => $field['type'],
        };

        $attrs = [
            'type'        => $type,
            'id'          => $field['id'],
            'name'        => $field['id'],
            'placeholder' => $field['placeholder'] ?? '',
            'value'       => $field['default_value'] ?? '',
        ];

        if ($field['required'] ?? false) $attrs['required'] = 'required';

        $validation = $field['validation'] ?? [];
        if (isset($validation['min_length'])) $attrs['minlength'] = $validation['min_length'];
        if (isset($validation['max_length'])) $attrs['maxlength'] = $validation['max_length'];
        if (isset($validation['pattern']))    $attrs['pattern']   = $validation['pattern'];

        return '<input class="klytos-form__input" ' . $this->buildAttrs($attrs) . '>';
    }

    private function renderNumberField(array $field): string
    {
        $validation = $field['validation'] ?? [];
        $attrs = [
            'type'  => $field['type'],
            'id'    => $field['id'],
            'name'  => $field['id'],
            'value' => $field['default_value'] ?? '',
        ];

        if ($field['required'] ?? false)    $attrs['required'] = 'required';
        if (isset($validation['min']))      $attrs['min']  = $validation['min'];
        if (isset($validation['max']))      $attrs['max']  = $validation['max'];
        if (isset($validation['step']))     $attrs['step'] = $validation['step'];

        $class = $field['type'] === 'range' ? 'klytos-form__range' : 'klytos-form__input';
        return '<input class="' . $class . '" ' . $this->buildAttrs($attrs) . '>';
    }

    private function renderTextarea(array $field): string
    {
        $validation = $field['validation'] ?? [];
        $attrs = [
            'id'          => $field['id'],
            'name'        => $field['id'],
            'placeholder' => $field['placeholder'] ?? '',
            'rows'        => $validation['rows'] ?? 5,
        ];

        if ($field['required'] ?? false) $attrs['required'] = 'required';
        if (isset($validation['min_length'])) $attrs['minlength'] = $validation['min_length'];
        if (isset($validation['max_length'])) $attrs['maxlength'] = $validation['max_length'];

        return '<textarea class="klytos-form__textarea" ' . $this->buildAttrs($attrs) . '>'
            . htmlspecialchars($field['default_value'] ?? '') . '</textarea>';
    }

    private function renderSelect(array $field): string
    {
        $attrs = ['id' => $field['id'], 'name' => $field['id']];
        if ($field['required'] ?? false) $attrs['required'] = 'required';
        if ($field['multiple'] ?? false) {
            $attrs['multiple'] = 'multiple';
            $attrs['name'] = $field['id'] . '[]';
        }

        $html = '<select class="klytos-form__select" ' . $this->buildAttrs($attrs) . '>';
        foreach ($field['options'] ?? [] as $opt) {
            $selected = ($field['default_value'] ?? '') === $opt['value'] ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($opt['value']) . '"' . $selected . '>'
                . htmlspecialchars($opt['label']) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    private function renderRadioGroup(array $field): string
    {
        $html = '<div class="klytos-form__radio-group">';
        foreach ($field['options'] ?? [] as $i => $opt) {
            $radioId = $field['id'] . '_' . $i;
            $checked = ($field['default_value'] ?? '') === $opt['value'] ? ' checked' : '';
            $html .= '<label class="klytos-form__radio-label" for="' . $radioId . '">';
            $html .= '<input type="radio" class="klytos-form__radio" id="' . $radioId . '" name="' . $field['id'] . '" value="' . htmlspecialchars($opt['value']) . '"' . $checked;
            if ($field['required'] ?? false) $html .= ' required';
            $html .= '>';
            $html .= '<span>' . htmlspecialchars($opt['label']) . '</span></label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderCheckbox(array $field): string
    {
        $checked = ($field['default_value'] ?? false) ? ' checked' : '';
        return '<input type="checkbox" class="klytos-form__checkbox" id="' . $field['id'] . '" name="' . $field['id'] . '"' . $checked
            . (($field['required'] ?? false) ? ' required' : '') . '>';
    }

    private function renderCheckboxGroup(array $field): string
    {
        $html = '<div class="klytos-form__checkbox-group">';
        foreach ($field['options'] ?? [] as $i => $opt) {
            $cbId = $field['id'] . '_' . $i;
            $html .= '<label class="klytos-form__checkbox-label" for="' . $cbId . '">';
            $html .= '<input type="checkbox" class="klytos-form__checkbox" id="' . $cbId . '" name="' . $field['id'] . '[]" value="' . htmlspecialchars($opt['value']) . '">';
            $html .= '<span>' . htmlspecialchars($opt['label']) . '</span></label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderConsent(array $field): string
    {
        return '<label class="klytos-form__consent-label">'
            . '<input type="checkbox" class="klytos-form__checkbox" id="' . $field['id'] . '" name="' . $field['id'] . '" required>'
            . '<span>' . ($field['consent_text'] ?? $field['label'] ?? '') . '</span>'
            . '</label>';
    }

    private function renderDateTimeField(array $field): string
    {
        $type = $field['type']; // 'date' o 'time'
        $attrs = [
            'type'  => $type,
            'id'    => $field['id'],
            'name'  => $field['id'],
            'value' => $field['default_value'] ?? '',
        ];
        if ($field['required'] ?? false) $attrs['required'] = 'required';

        $validation = $field['validation'] ?? [];
        if ($type === 'date') {
            if (isset($validation['min_date'])) $attrs['min'] = $validation['min_date'];
            if (isset($validation['max_date'])) $attrs['max'] = $validation['max_date'];
        }

        return '<input class="klytos-form__input" ' . $this->buildAttrs($attrs) . '>';
    }

    private function renderFileField(array $field): string
    {
        $attrs = [
            'type' => 'file',
            'id'   => $field['id'],
            'name' => $field['id'],
        ];
        if ($field['required'] ?? false) $attrs['required'] = 'required';

        $validation = $field['validation'] ?? [];
        if (!empty($validation['allowed_types'])) {
            $attrs['accept'] = implode(',', array_map(fn($t) => '.' . $t, $validation['allowed_types']));
        }
        if (isset($validation['max_files']) && $validation['max_files'] > 1) {
            $attrs['multiple'] = 'multiple';
            $attrs['name'] = $field['id'] . '[]';
        }

        return '<input class="klytos-form__file" ' . $this->buildAttrs($attrs) . '>';
    }

    private function renderHiddenField(array $field): string
    {
        return '<input type="hidden" name="' . htmlspecialchars($field['id']) . '" value="' . htmlspecialchars($field['default_value'] ?? '') . '">';
    }

    private function buildAttrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === true || $key === $value) {
                $parts[] = $key;
            } else {
                $parts[] = $key . '="' . htmlspecialchars((string)$value) . '"';
            }
        }
        return implode(' ', $parts);
    }

    private function getCsrfToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }
}
```

### 5.2 CSS base de formularios

El CSS se incluira en el renderizado del formulario. Usa CSS variables de Klytos para integrarse con el tema:

```css
/* --- Klytos Forms Base Styles --- */
.klytos-form {
    font-family: inherit;
    max-width: 640px;
    margin: 0 auto;
}

.klytos-form--stacked .klytos-form__field {
    margin-bottom: 1.25rem;
}

.klytos-form--inline .klytos-form__field {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.klytos-form__label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.375rem;
    color: var(--klytos-text, #1a1a2e);
    font-size: 0.9375rem;
}

.klytos-form__required {
    color: #dc2626;
    margin-left: 0.125rem;
}

.klytos-form__input,
.klytos-form__textarea,
.klytos-form__select,
.klytos-form__file {
    display: block;
    width: 100%;
    padding: 0.625rem 0.875rem;
    font-size: 0.9375rem;
    line-height: 1.5;
    color: var(--klytos-text, #1a1a2e);
    background-color: var(--klytos-surface, #ffffff);
    border: 1px solid var(--klytos-border, #d1d5db);
    border-radius: var(--klytos-radius, 0.5rem);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    font-family: inherit;
    box-sizing: border-box;
}

.klytos-form__input:focus,
.klytos-form__textarea:focus,
.klytos-form__select:focus {
    outline: none;
    border-color: var(--klytos-primary, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.klytos-form__input:invalid:not(:placeholder-shown),
.klytos-form__textarea:invalid:not(:placeholder-shown) {
    border-color: #dc2626;
}

.klytos-form__textarea {
    resize: vertical;
    min-height: 100px;
}

.klytos-form__select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    padding-right: 2.5rem;
}

.klytos-form__radio-group,
.klytos-form__checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.klytos-form__radio-label,
.klytos-form__checkbox-label,
.klytos-form__consent-label {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.9375rem;
    color: var(--klytos-text, #1a1a2e);
}

.klytos-form__radio,
.klytos-form__checkbox {
    margin-top: 0.1875rem;
    accent-color: var(--klytos-primary, #6366f1);
}

.klytos-form__section-title {
    font-size: 1.125rem;
    font-weight: 600;
    padding-top: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--klytos-border, #d1d5db);
    margin-bottom: 1rem;
    color: var(--klytos-text, #1a1a2e);
}

.klytos-form__actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--klytos-border, #e5e7eb);
    margin-top: 0.5rem;
}

.klytos-form__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1.5rem;
    font-size: 0.9375rem;
    font-weight: 500;
    border-radius: var(--klytos-radius, 0.5rem);
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
    font-family: inherit;
}

.klytos-form__btn--primary {
    background-color: var(--klytos-primary, #6366f1);
    color: #ffffff;
}

.klytos-form__btn--primary:hover {
    filter: brightness(1.1);
}

.klytos-form__btn--secondary {
    background-color: transparent;
    color: var(--klytos-text, #1a1a2e);
    border-color: var(--klytos-border, #d1d5db);
}

.klytos-form__btn--secondary:hover {
    background-color: var(--klytos-surface, #f3f4f6);
}

.klytos-form__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.klytos-form__field-error {
    color: #dc2626;
    font-size: 0.8125rem;
    margin-top: 0.25rem;
    min-height: 0;
}

.klytos-form__success {
    padding: 1rem 1.25rem;
    background-color: #ecfdf5;
    border: 1px solid #6ee7b7;
    border-radius: var(--klytos-radius, 0.5rem);
    color: #065f46;
    font-size: 0.9375rem;
}

.klytos-form__error {
    padding: 1rem 1.25rem;
    background-color: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: var(--klytos-radius, 0.5rem);
    color: #991b1b;
    font-size: 0.9375rem;
}

/* --- Stepper --- */
.klytos-form-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-bottom: 2rem;
    padding: 0 1rem;
}

.klytos-form-step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.klytos-form-step__number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    font-size: 0.8125rem;
    font-weight: 600;
    flex-shrink: 0;
}

.klytos-form-step--pending .klytos-form-step__number {
    background-color: var(--klytos-surface, #f3f4f6);
    color: var(--klytos-text-muted, #6b7280);
    border: 1px solid var(--klytos-border, #d1d5db);
}

.klytos-form-step--active .klytos-form-step__number {
    background-color: var(--klytos-primary, #6366f1);
    color: #ffffff;
}

.klytos-form-step--completed .klytos-form-step__number {
    background-color: #10b981;
    color: #ffffff;
}

.klytos-form-step__title {
    font-size: 0.8125rem;
    color: var(--klytos-text-muted, #6b7280);
}

.klytos-form-step--active .klytos-form-step__title {
    color: var(--klytos-text, #1a1a2e);
    font-weight: 500;
}

.klytos-form-step__connector {
    flex: 1;
    height: 1px;
    background-color: var(--klytos-border, #d1d5db);
    margin: 0 0.75rem;
    min-width: 1.5rem;
}

/* --- Range slider --- */
.klytos-form__range {
    width: 100%;
    accent-color: var(--klytos-primary, #6366f1);
}

/* --- File input --- */
.klytos-form__file {
    padding: 0.5rem;
}

/* --- Responsive --- */
@media (max-width: 640px) {
    .klytos-form {
        padding: 0 0.5rem;
    }
    .klytos-form__actions {
        flex-direction: column;
    }
    .klytos-form__btn {
        width: 100%;
    }
    .klytos-form-step__title {
        display: none;
    }
}
```

**NOTA:** Este CSS se inyecta como `<style>` dentro del HTML del formulario para evitar cargas externas innecesarias. Solo se inyecta una vez por pagina aunque haya multiples formularios.

---

## 6. Insercion de formularios en paginas

### 6.1 Shortcode

Los formularios se insertan en paginas mediante la etiqueta:

```
{{form:contact-form}}
```

Donde `contact-form` es el ID del formulario. El sistema de templates de Klytos debe resolver esta etiqueta llamando a `FormRenderer::render()`.

### 6.2 Registro del shortcode

En el motor de templates (donde se resuelven las variables `{{ }}`), anadir la deteccion de shortcodes de formulario:

```php
// En el resolutor de templates, anadir:
$content = preg_replace_callback(
    '/\{\{form:([a-z0-9\-_]+)\}\}/',
    function ($matches) use ($app) {
        $formRenderer = new FormRenderer($app->getFormManager());
        return $formRenderer->render($matches[1]);
    },
    $content
);
```

### 6.3 Bloque dedicado (opcional, para el page builder)

Si el page builder lo soporta, registrar un bloque `klytos-form` que permita seleccionar un formulario de la lista y renderizarlo:

```php
// Registro de bloque
[
    'type'       => 'klytos-form',
    'label'      => 'Formulario',
    'icon'       => 'form',
    'attributes' => [
        'form_id' => ['type' => 'string', 'default' => ''],
    ],
    'render'     => function ($attrs) use ($app) {
        $renderer = new FormRenderer($app->getFormManager());
        return $renderer->render($attrs['form_id'] ?? '');
    },
]
```

---

## 7. API endpoints

### 7.1 Endpoint publico: envio de formulario

Archivo: `public/api/forms/submit.php` (o ruta equivalente)

```php
// POST /api/forms/submit
// Content-Type: multipart/form-data

// Parametros obligatorios:
// _form_id: ID del formulario
// _csrf_token: token CSRF
// (resto de campos del formulario)

$formId = $_POST['_form_id'] ?? '';

$meta = [
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'referrer'   => $_SERVER['HTTP_REFERER'] ?? '',
    'page_url'   => $_POST['_page_url'] ?? '',
    'locale'     => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
];

$result = $app->getFormManager()->submitForm($formId, $_POST, $_FILES, $meta);

header('Content-Type: application/json');
echo json_encode($result);
```

### 7.2 Endpoints de administracion

Archivo: `admin/api/forms.php`

Acciones soportadas (todas requieren autenticacion admin + CSRF):

**Formularios:**
- `GET ?action=list&status=active` - Listar formularios
- `GET ?action=get&id=contact-form` - Obtener un formulario
- `POST ?action=create` (body JSON) - Crear formulario
- `POST ?action=update&id=contact-form` (body JSON) - Actualizar formulario
- `POST ?action=delete&id=contact-form&delete_entries=0` - Eliminar formulario
- `POST ?action=duplicate&id=contact-form` - Duplicar formulario

**Campos:**
- `POST ?action=add_field&form_id=contact-form` (body JSON) - Anadir campo
- `POST ?action=update_field&form_id=contact-form&field_id=field_name` (body JSON) - Actualizar campo
- `POST ?action=remove_field&form_id=contact-form&field_id=field_name` - Eliminar campo
- `POST ?action=reorder_fields&form_id=contact-form` (body JSON: `{"order": ["field_1", "field_2"]}`) - Reordenar campos

**Entradas:**
- `GET ?action=entries&form_id=contact-form&status=unread&page=1&per_page=20&search=texto` - Listar entradas
- `GET ?action=entry&id=entry_a1b2c3d4` - Ver una entrada
- `POST ?action=update_entry_status&id=entry_a1b2c3d4&status=read` - Cambiar estado
- `POST ?action=add_note&id=entry_a1b2c3d4` (body: `{"note": "texto"}`) - Anadir nota
- `POST ?action=delete_entry&id=entry_a1b2c3d4` - Eliminar entrada
- `POST ?action=delete_entries&form_id=contact-form` - Eliminar todas las entradas de un formulario
- `GET ?action=export&form_id=contact-form&format=csv` - Exportar entradas

**Estadisticas:**
- `GET ?action=stats&form_id=contact-form` - Estadisticas del formulario

---

## 8. Cambios en la base de datos (DatabaseStorage)

### 8.1 Nuevos indices

Anadir al array `INDEX_FIELDS` de `DatabaseStorage`:

```php
private const INDEX_FIELDS = [
    // ... campos existentes ...
    'forms'        => ['status' => 'idx_type'],
    'form-entries' => ['form_id' => 'idx_type', 'status' => 'idx_slug'],
];
```

### 8.2 Anadir a las tablas por defecto

En el metodo `createTables()`, anadir las nuevas colecciones:

```php
$collections = [
    // ... existentes ...
    'forms',          // <-- NUEVO
    'form-entries',   // <-- NUEVO
];
```

### 8.3 Auto-creacion

Como en el resto de colecciones, si la tabla no existe se crea automaticamente en el primer `write()`. No se necesita migracion explicita.

---

## 9. Panel de administracion

### 9.1 Forms > Lista de formularios

Archivo: `admin/forms.php`

Funcionalidad:
- Tabla con: Titulo, Estado, Campos, Entradas (count), Ultima entrada, Acciones
- Acciones: Editar, Duplicar, Ver entradas, Eliminar
- Boton "Nuevo formulario" (abre modal o pagina de edicion)
- Filtro por estado (activo/inactivo/todos)

### 9.2 Forms > Editor de formulario

Archivo: `admin/form-editor.php`

Pestanas:
- **Campos**: lista de campos con drag&drop para reordenar, boton para anadir campo, edicion inline
- **Configuracion**: titulo, descripcion, boton de envio, mensaje de exito, layout, multi-paso
- **Notificaciones**: lista de notificaciones configuradas, edicion, condiciones
- **Anti-spam**: honeypot, rate limiting
- **Insertar**: muestra el shortcode `{{form:id}}` para copiar y pegar

### 9.3 Forms > Entradas

Archivo: `admin/form-entries.php`

Funcionalidad:
- Selector de formulario
- Tabla con: ID, Fecha, Campos principales (configurable), Estado, Acciones
- Filtros: estado, fecha desde/hasta, busqueda
- Detalle de entrada en modal o pagina aparte
- Marcar como leido/importante
- Exportar CSV/JSON
- Eliminar individual o en bloque
- Estadisticas resumen arriba: total, nuevas hoy, esta semana

### 9.4 Entradas en el menu

```php
// En el sidebar, nueva seccion "Formularios"
[
    'section' => 'Formularios',
    'icon'    => 'form',
    'items'   => [
        ['title' => 'Todos los formularios', 'slug' => 'forms',        'icon' => 'list'],
        ['title' => 'Entradas',              'slug' => 'form-entries', 'icon' => 'inbox'],
    ],
]
```

---

## 10. Integracion con MCP Tools (AI-first)

Estas herramientas permiten a la IA crear, modificar, consultar y gestionar formularios y sus entradas completamente via MCP:

### 10.1 Herramientas para formularios

```php
[
    'name'        => 'forms_create',
    'description' => 'Create a new form with fields, notifications, and settings. Provide the complete form definition.',
    'parameters'  => [
        'title'         => 'string (required)',
        'description'   => 'string',
        'id'            => 'string (optional, auto-generated from title if omitted)',
        'fields'        => 'array of field objects (required)',
        'settings'      => 'object (optional, merged with defaults)',
        'notifications' => 'array of notification objects (optional)',
        'anti_spam'     => 'object (optional, merged with defaults)',
    ],
],
[
    'name'        => 'forms_get',
    'description' => 'Get a form definition by ID, including all fields, settings, and notifications.',
    'parameters'  => [
        'id' => 'string (required)',
    ],
],
[
    'name'        => 'forms_list',
    'description' => 'List all forms, optionally filtered by status.',
    'parameters'  => [
        'status' => 'string (optional: active, inactive)',
    ],
],
[
    'name'        => 'forms_update',
    'description' => 'Update a form. Only provided fields are updated (partial update).',
    'parameters'  => [
        'id'            => 'string (required)',
        'title'         => 'string',
        'description'   => 'string',
        'status'        => 'string',
        'fields'        => 'array of field objects (replaces all fields)',
        'settings'      => 'object (merged with existing)',
        'notifications' => 'array (replaces all notifications)',
        'anti_spam'     => 'object (merged with existing)',
    ],
],
[
    'name'        => 'forms_delete',
    'description' => 'Delete a form and optionally all its entries.',
    'parameters'  => [
        'id'             => 'string (required)',
        'delete_entries' => 'boolean (default false)',
        'confirm'        => 'boolean (required, must be true)',
    ],
],
[
    'name'        => 'forms_duplicate',
    'description' => 'Duplicate an existing form with a new title.',
    'parameters'  => [
        'id'        => 'string (required)',
        'new_title' => 'string (optional)',
    ],
],
```

### 10.2 Herramientas para campos

```php
[
    'name'        => 'forms_add_field',
    'description' => 'Add a new field to an existing form.',
    'parameters'  => [
        'form_id'  => 'string (required)',
        'field'    => 'object (required): {type, label, id?, required?, placeholder?, options?, validation?, conditional?, step?, css_class?}',
        'position' => 'integer (optional, insert at this position)',
    ],
],
[
    'name'        => 'forms_update_field',
    'description' => 'Update an existing field in a form. Only provided properties are updated.',
    'parameters'  => [
        'form_id'  => 'string (required)',
        'field_id' => 'string (required)',
        'updates'  => 'object: properties to update',
    ],
],
[
    'name'        => 'forms_remove_field',
    'description' => 'Remove a field from a form. Also cleans up conditional rules referencing this field.',
    'parameters'  => [
        'form_id'  => 'string (required)',
        'field_id' => 'string (required)',
    ],
],
[
    'name'        => 'forms_reorder_fields',
    'description' => 'Reorder fields in a form by providing field IDs in desired order.',
    'parameters'  => [
        'form_id' => 'string (required)',
        'order'   => 'array of field ID strings (required)',
    ],
],
```

### 10.3 Herramientas para entradas

```php
[
    'name'        => 'forms_list_entries',
    'description' => 'List form entries with filters, pagination, and search.',
    'parameters'  => [
        'form_id'   => 'string (required)',
        'status'    => 'string (optional: unread, read, starred, trash)',
        'search'    => 'string (optional: search in entry data)',
        'date_from' => 'string (optional: ISO date)',
        'date_to'   => 'string (optional: ISO date)',
        'page'      => 'integer (default 1)',
        'per_page'  => 'integer (default 20)',
    ],
],
[
    'name'        => 'forms_get_entry',
    'description' => 'Get a specific form entry by ID with all data and metadata.',
    'parameters'  => [
        'entry_id' => 'string (required)',
    ],
],
[
    'name'        => 'forms_update_entry_status',
    'description' => 'Update the status of a form entry.',
    'parameters'  => [
        'entry_id' => 'string (required)',
        'status'   => 'string (required: unread, read, starred, trash)',
    ],
],
[
    'name'        => 'forms_delete_entry',
    'description' => 'Permanently delete a form entry.',
    'parameters'  => [
        'entry_id' => 'string (required)',
        'confirm'  => 'boolean (required, must be true)',
    ],
],
[
    'name'        => 'forms_export_entries',
    'description' => 'Export all entries of a form as CSV or JSON.',
    'parameters'  => [
        'form_id' => 'string (required)',
        'format'  => 'string (csv or json, default csv)',
    ],
],
[
    'name'        => 'forms_stats',
    'description' => 'Get statistics for a form: total entries, unread, by period, etc.',
    'parameters'  => [
        'form_id' => 'string (required)',
    ],
],
```

### 10.4 Ejemplo de uso via IA

Un usuario podria decir en el chat:

> "Crea un formulario de solicitud de presupuesto con nombre, email, telefono, tipo de servicio (diseno web, SEO, marketing), presupuesto estimado (slider de 500 a 50000), y mensaje. Que el campo telefono solo aparezca si el tipo de servicio es diseno web. Envia notificacion al admin y confirmacion al usuario."

La IA usaria las tools MCP para:
1. `forms_create` con toda la definicion del formulario, campos, condicionales y notificaciones.
2. Devolver al usuario el shortcode `{{form:solicitud-presupuesto}}` para insertar en la pagina deseada.

O de forma incremental:
1. `forms_create` con campos basicos.
2. `forms_add_field` para anadir campos adicionales.
3. `forms_update` para configurar notificaciones.
4. `forms_update_field` para anadir condicionales.

---

## 11. Instanciacion en App

### 11.1 Registrar FormManager en el kernel

En `app.php`, al igual que se instancia `AssetManager` y `OptionsManager`:

```php
// En App::boot() o equivalente

$this->conditionalEngine = new FormConditionalEngine();

$this->formManager = new FormManager(
    $this->storage,
    $this->conditionalEngine,
    $this->assetManager  // puede ser null si no hay asset manager
);
```

### 11.2 Metodo getter

```php
public function getFormManager(): FormManager
{
    return $this->formManager;
}
```

### 11.3 Funciones helper publicas

```php
/**
 * Obtener el FormManager.
 */
function klytos_forms(): FormManager
{
    return App::getInstance()->getFormManager();
}

/**
 * Renderizar un formulario como HTML.
 */
function klytos_render_form(string $formId): string
{
    $renderer = new FormRenderer(klytos_forms());
    return $renderer->render($formId);
}
```

---

## 12. Hooks disponibles

El sistema de formularios dispara los siguientes hooks para permitir extensibilidad por plugins:

| Hook | Parametros | Descripcion |
|------|-----------|-------------|
| `form.after_create` | `$form` | Despues de crear un formulario |
| `form.after_update` | `$form` | Despues de actualizar un formulario |
| `form.before_delete` | `$formId` | Antes de eliminar un formulario |
| `form.after_delete` | `$formId` | Despues de eliminar un formulario |
| `form.entry_created` | `$entry`, `$form` | Despues de crear una entrada (envio del formulario) |
| `form.before_validate` | `$form`, `$data` | Antes de validar un envio |
| `form.after_validate` | `$form`, `$data`, `$errors` | Despues de validar (permite anadir errores custom) |
| `form.notification_sent` | `$notification`, `$entry`, `$success` | Despues de intentar enviar una notificacion |
| `form.before_render` | `$form`, `$options` | Antes de renderizar (permite modificar el form) |

---

## 13. Orden de implementacion

1. Crear `core/form-conditional-engine.php` con la clase `FormConditionalEngine`.
2. Crear `core/form-manager.php` con la clase `FormManager` (CRUD de formularios + campos + envios + entradas).
3. Anadir `forms` y `form-entries` a `INDEX_FIELDS` en `DatabaseStorage` y a `createTables()`.
4. Registrar `FormManager` y `FormConditionalEngine` en `App` (`app.php`).
5. Anadir funciones helper publicas (`klytos_forms()`, `klytos_render_form()`).
6. Crear `core/form-renderer.php` con la clase `FormRenderer`.
7. Crear `public/js/klytos-forms.js` con el motor de condicionales frontend y manejo AJAX.
8. Integrar el shortcode `{{form:id}}` en el resolutor de templates.
9. Crear `public/api/forms/submit.php` (endpoint publico de envio).
10. Crear `admin/api/forms.php` (endpoints de administracion).
11. Crear `admin/forms.php` (listado de formularios).
12. Crear `admin/form-editor.php` (editor de formulario con pestanas).
13. Crear `admin/form-entries.php` (visor de entradas).
14. Anadir entradas al menu lateral de admin.
15. Registrar MCP tools para formularios, campos y entradas.
16. Probar: crear formulario de ejemplo via MCP, insertarlo en pagina, enviar, verificar entrada y email.

---

## 14. Notas importantes

- **AI-first:** Todo el sistema esta disenado para que la IA pueda crear un formulario completo (con campos, condicionales, notificaciones) en una sola llamada MCP. El panel admin es complementario, no primario.
- **Los condicionales se evaluan tanto en frontend (JS) como en backend (PHP).** El frontend para UX reactiva, el backend para seguridad (nunca confiar solo en JS).
- **Los campos ocultos por condicionales no se validan ni se incluyen en la entrada.** Esto evita errores de validacion en campos que el usuario no puede ver.
- **El honeypot es invisible y no requiere JavaScript.** Los bots que rellenan todos los campos seran detectados silenciosamente.
- **Las notificaciones usan merge tags `{{field_id}}`** para insertar valores de campos. Tambien se soportan variables del sistema como `{{site_name}}`, `{{site_email}}`, `{{entry_id}}`, `{{all_fields}}`.
- **El CSS usa las CSS variables de Klytos** (`--klytos-primary`, `--klytos-border`, etc.), por lo que se adapta automaticamente al tema activo. Si se quiere personalizar un formulario especifico, se puede usar `css_class` en el formulario o en campos individuales.
- **Nunca fallar silenciosamente:** si un formulario no existe o esta inactivo al intentar renderizarlo, se devuelve un comentario HTML invisible. Si un envio falla la validacion, se devuelven errores claros campo por campo.
- **Los archivos subidos se integran con AssetManager** para reutilizar la infraestructura de medios existente, incluyendo categorias y seguimiento de uso.
- **El rate limiting es por IP + formulario.** Configurable por formulario. Un valor de 0 desactiva el rate limiting.
- **Exportacion:** CSV para uso en hojas de calculo, JSON para integraciones automaticas. Ambos accesibles via API admin y MCP.
