/**
 * Form Builder Script
 * Manages dynamic creation, modification, and ordering of form fields.
 */
function initializeFormBuilder(containerId, jsonInputId, initialData = [], fieldTypesConfig = {}) {
    const fieldsContainer = document.getElementById(containerId);
    const formFieldsJsonInput = document.getElementById(jsonInputId);
    const placeholderTextEl = fieldsContainer ? fieldsContainer.querySelector('.placeholder-text') : null;

    let fieldsData = Array.isArray(initialData) ? initialData : [];
    let internalFieldCounter = 0; // Used for generating unique IDs for new fields

    if (!fieldsContainer || !formFieldsJsonInput) {
        console.error("Form builder container or JSON input not found.");
        return;
    }

    const defaultFieldConfig = {
        has_options: false,
        has_placeholder: true,
        has_min_max_value: false,
        has_max_length: true,
        has_helper_text: true,
        has_file_types: false,
    };

    function getFieldTypeSpecificConfig(type) {
        return fieldTypesConfig.types && fieldTypesConfig.types[type]
               ? { ...defaultFieldConfig, ...fieldTypesConfig.types[type] }
               : { ...defaultFieldConfig, label: type, icon: '' }; // Fallback
    }

    function renderAllFields() {
        fieldsContainer.querySelectorAll('.form-field-item').forEach(el => el.remove()); // Clear existing elements first

        if (fieldsData.length === 0 && placeholderTextEl) {
            placeholderTextEl.classList.remove('d-none');
        } else if (placeholderTextEl) {
            placeholderTextEl.classList.add('d-none');
        }

        fieldsData.forEach((fieldDataObject, index) => {
            // Ensure each field has a unique client-side ID if not already present
            if (!fieldDataObject.id) {
                fieldDataObject.id = `field_${new Date().getTime()}_${index}`;
            }
            const fieldElement = createFieldElement(fieldDataObject);
            fieldsContainer.appendChild(fieldElement);
        });
        updateJsonOutput();
        updateSortableState();
    }

    function createFieldElement(fieldData) {
        const wrapperTemplate = document.getElementById('form-field-wrapper-template');
        if (!wrapperTemplate) {
            console.error("Form field wrapper template not found!");
            return document.createElement('div'); // Return empty div to prevent further errors
        }
        const clone = wrapperTemplate.content.cloneNode(true);
        const fieldItem = clone.querySelector('.form-field-item');
        const fieldTypeConfig = getFieldTypeSpecificConfig(fieldData.type);

        fieldItem.dataset.fieldId = fieldData.id;
        fieldItem.dataset.fieldType = fieldData.type;

        const iconLabelSpan = fieldItem.querySelector('.field-type-icon-label');
        if (iconLabelSpan) {
            iconLabelSpan.innerHTML = `${fieldTypeConfig.icon || ''} ${fieldTypeConfig.label || fieldData.type}`;
        }

        // Populate common properties
        fieldItem.querySelectorAll('.field-property').forEach(input => {
            const property = input.dataset.property;
            if (property === 'type') {
                input.value = fieldData.type;
            } else if (property === 'required') {
                input.checked = fieldData.required || false;
                const uniqueId = `field_required_${fieldData.id}`;
                input.id = uniqueId;
                const label = fieldItem.querySelector(`label[for='field_required_placeholder_id']`);
                if (label) label.setAttribute('for', uniqueId);
            } else if (fieldData[property] !== undefined) {
                if (input.type === 'checkbox') {
                    input.checked = fieldData[property];
                } else {
                    input.value = fieldData[property];
                }
            }
        });

        // Inject specific properties based on field type config
        const specificPropsContainer = fieldItem.querySelector('.specific-properties');
        if (specificPropsContainer) {
            if (fieldTypeConfig.has_options) {
                const optionsTemplate = document.getElementById('prop-options-template');
                if (optionsTemplate) {
                    const optionsClone = optionsTemplate.content.cloneNode(true);
                    const textarea = optionsClone.querySelector('.options-input');
                    if (textarea && fieldData.options && Array.isArray(fieldData.options)) {
                        textarea.value = fieldData.options.join('\n');
                    }
                     // Ensure event listener is attached for options textarea
                    textarea.addEventListener('change', () => updateFieldDataFromElement(fieldData.id, textarea));
                    textarea.addEventListener('keyup', () => updateFieldDataFromElement(fieldData.id, textarea));
                    specificPropsContainer.appendChild(optionsClone);
                }
            }
            if (fieldTypeConfig.has_min_max_value) {
                const minMaxTemplate = document.getElementById('prop-min-max-value-template');
                if (minMaxTemplate) specificPropsContainer.appendChild(minMaxTemplate.content.cloneNode(true));
            }
            if (fieldTypeConfig.has_max_length) {
                 const maxLengthTemplate = document.getElementById('prop-max-length-template');
                 if(maxLengthTemplate) specificPropsContainer.appendChild(maxLengthTemplate.content.cloneNode(true));
            }
             if (fieldTypeConfig.has_file_types) { // Example for file types
                const fileTypesTemplate = document.getElementById('prop-file-types-template');
                if (fileTypesTemplate) {
                    const ftClone = fileTypesTemplate.content.cloneNode(true);
                    const input = ftClone.querySelector('.field-property[data-property="file_types_text"]');
                    if (input && fieldData.file_types && Array.isArray(fieldData.file_types)) {
                        input.value = fieldData.file_types.join(', ');
                    }
                    specificPropsContainer.appendChild(ftClone);
                }
            }
            // Repopulate values for specific properties after injecting them
            fieldItem.querySelectorAll('.specific-properties .field-property').forEach(input => {
                const property = input.dataset.property;
                 if (fieldData[property] !== undefined) {
                    if (input.type === 'checkbox') input.checked = fieldData[property];
                    else input.value = fieldData[property];
                }
            });
        }

        // Show/hide common advanced properties based on config
        if (fieldItem.querySelector('.field-prop-placeholder')) fieldItem.querySelector('.field-prop-placeholder').style.display = fieldTypeConfig.has_placeholder ? '' : 'none';
        if (fieldItem.querySelector('.field-prop-helper-text')) fieldItem.querySelector('.field-prop-helper-text').style.display = fieldTypeConfig.has_helper_text ? '' : 'none';


        // Add event listeners for all properties (common and specific)
        fieldItem.querySelectorAll('.field-property').forEach(input => {
            input.addEventListener('change', () => updateFieldDataFromElement(fieldData.id, input));
            input.addEventListener('keyup', () => updateFieldDataFromElement(fieldData.id, input));
        });
        fieldItem.querySelector('.remove-field-btn').addEventListener('click', () => removeField(fieldData.id));

        return fieldItem;
    }

    function updateFieldDataFromElement(fieldId, inputElement) {
        const property = inputElement.dataset.property;
        const value = inputElement.type === 'checkbox' ? inputElement.checked : inputElement.value;
        updateFieldDataObject(fieldId, property, value);
    }

    function addField(type) {
        internalFieldCounter++;
        const newFieldId = `newfield_${new Date().getTime()}_${internalFieldCounter}`;
        const fieldTypeConf = getFieldTypeSpecificConfig(type);
        const newFieldData = {
            id: newFieldId,
            type: type,
            label: fieldTypeConf.label || 'فیلد جدید',
            placeholder: '',
            helper_text: '',
            required: false,
            options: (fieldTypeConf.has_options) ? ['گزینه ۱', 'گزینه ۲'] : [],
            // Initialize other type-specific properties if needed
            min_value: null, max_value: null, max_length: null, file_types: []
        };
        fieldsData.push(newFieldData);

        if (placeholderTextEl) placeholderTextEl.classList.add('d-none');

        const newElement = createFieldElement(newFieldData);
        fieldsContainer.appendChild(newElement);
        updateJsonOutput();
        updateSortableState();
    }

    function removeField(fieldId) {
        fieldsData = fieldsData.filter(f => f.id !== fieldId);
        const fieldElement = fieldsContainer.querySelector(`.form-field-item[data-field-id="${fieldId}"]`);
        if (fieldElement) fieldElement.remove();

        if (fieldsData.length === 0 && placeholderTextEl) {
            placeholderTextEl.classList.remove('d-none');
        }
        updateJsonOutput();
        updateSortableState();
    }

    function updateFieldDataObject(fieldId, property, value) {
        const fieldIndex = fieldsData.findIndex(f => f.id === fieldId);
        if (fieldIndex > -1) {
            if (property === 'options_text') {
                fieldsData[fieldIndex].options = value.split('\n').map(opt => opt.trim()).filter(opt => opt !== '');
            } else if (property === 'file_types_text') { // Example for file types
                 fieldsData[fieldIndex].file_types = value.split(',').map(ft => ft.trim().toLowerCase()).filter(ft => ft !== '');
            }
            else {
                fieldsData[fieldIndex][property] = value;
            }
            updateJsonOutput();
        }
    }

    function updateFieldsOrder() {
        const orderedFields = [];
        fieldsContainer.querySelectorAll('.form-field-item').forEach(itemElement => {
            const fieldId = itemElement.dataset.fieldId;
            const field = fieldsData.find(f => f.id === fieldId);
            if (field) {
                orderedFields.push(field);
            }
        });
        fieldsData = orderedFields;
    }

    function updateJsonOutput() {
        formFieldsJsonInput.value = JSON.stringify(fieldsData);
    }

    function updateSortableState() {
        // Placeholder for any logic needed after sorting or DOM changes
        // e.g., re-initializing plugins on sorted items if necessary
    }

    // Attach event listeners to "Add Field" buttons
    document.querySelectorAll('.add-field-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            addField(e.currentTarget.dataset.fieldType);
        });
    });

    // Initialize SortableJS for drag & drop
    if (typeof Sortable !== 'undefined') {
        new Sortable(fieldsContainer, {
            animation: 150,
            handle: '.handle-sort',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function (evt) {
                // Update fieldsData array order based on new DOM order
                const itemEl = evt.item; // dragged HTMLElement
                const fieldId = itemEl.dataset.fieldId;
                const oldIndex = fieldsData.findIndex(f => f.id === fieldId);
                const newIndex = Array.from(fieldsContainer.children).indexOf(itemEl);

                if (oldIndex !== -1 && newIndex !== -1 && oldIndex !== newIndex) {
                    const [movedField] = fieldsData.splice(oldIndex, 1);
                    fieldsData.splice(newIndex, 0, movedField);
                }
                updateJsonOutput();
            }
        });
    } else {
        console.warn('Sortable.min.js not loaded. Drag & drop sorting will not be available.');
    }

    // Initial render of fields from `initialData`
    renderAllFields();

    // Ensure JSON is up-to-date on form submission
    const form = fieldsContainer.closest('form'); // Find the parent form
    if (form) {
        form.addEventListener('submit', function() {
            updateJsonOutput();
        });
    } else {
        console.warn("Form element not found for form builder. JSON output on submit might not work.");
    }
}
