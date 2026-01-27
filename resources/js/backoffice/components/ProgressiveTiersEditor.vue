<template>
  <div class="progressive-tiers-editor">
    <div class="tiers-header">
      <span class="header-label">Прогрессивная шкала скидок</span>
      <button type="button" class="btn-add" @click="addTier">
        + Добавить порог
      </button>
    </div>

    <div v-if="localTiers.length === 0" class="empty-state">
      <p>Шкала скидок пуста. Добавьте пороги суммы заказа.</p>
    </div>

    <div class="tiers-list">
      <div
        v-for="(tier, index) in localTiers"
        :key="index"
        class="tier-row"
      >
        <div class="tier-field">
          <label>От суммы</label>
          <div class="input-with-suffix">
            <input
              v-model.number="tier.min_amount"
              type="number"
              min="0"
              step="100"
              placeholder="1000"
              @input="emitUpdate"
            />
            <span class="suffix">руб.</span>
          </div>
        </div>

        <div class="tier-field">
          <label>Скидка</label>
          <div class="input-with-suffix">
            <input
              v-model.number="tier.discount_percent"
              type="number"
              min="0"
              max="100"
              step="0.5"
              placeholder="5"
              @input="emitUpdate"
            />
            <span class="suffix">%</span>
          </div>
        </div>

        <button
          type="button"
          class="btn-remove"
          @click="removeTier(index)"
          title="Удалить порог"
        >
          &times;
        </button>
      </div>
    </div>

    <div v-if="localTiers.length > 0" class="preview">
      <div class="preview-title">Предпросмотр:</div>
      <div class="preview-content">
        <div
          v-for="(tier, index) in sortedTiers"
          :key="index"
          class="preview-item"
        >
          <span class="preview-range">
            {{ formatCurrency(tier.min_amount) }} руб.
            <span v-if="getNextTierAmount(index)">
              &ndash; {{ formatCurrency(getNextTierAmount(index) - 1) }} руб.
            </span>
            <span v-else>и выше</span>
          </span>
          <span class="preview-discount">-{{ tier.discount_percent }}%</span>
        </div>
      </div>
    </div>

    <div v-if="validationErrors.length > 0" class="validation-errors">
      <p v-for="(error, index) in validationErrors" :key="index" class="error">
        {{ error }}
      </p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProgressiveTiersEditor',
  props: {
    modelValue: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      localTiers: [],
    };
  },
  computed: {
    sortedTiers() {
      return [...this.localTiers].sort((a, b) => a.min_amount - b.min_amount);
    },
    validationErrors() {
      const errors = [];

      // Check for duplicate amounts
      const amounts = this.localTiers.map(t => t.min_amount);
      const duplicates = amounts.filter((item, index) => amounts.indexOf(item) !== index);
      if (duplicates.length > 0) {
        errors.push('Обнаружены дублирующиеся пороги суммы');
      }

      // Check for zero amounts
      if (this.localTiers.some(t => !t.min_amount || t.min_amount <= 0)) {
        errors.push('Сумма порога должна быть больше 0');
      }

      // Check for invalid discounts
      if (this.localTiers.some(t => t.discount_percent < 0 || t.discount_percent > 100)) {
        errors.push('Процент скидки должен быть от 0 до 100');
      }

      // Check for progressive discount (higher amount = higher discount)
      const sorted = this.sortedTiers;
      for (let i = 1; i < sorted.length; i++) {
        if (sorted[i].discount_percent <= sorted[i - 1].discount_percent) {
          errors.push('Скидка должна увеличиваться с ростом суммы заказа');
          break;
        }
      }

      return errors;
    },
  },
  watch: {
    modelValue: {
      handler(newVal) {
        if (JSON.stringify(newVal) !== JSON.stringify(this.localTiers)) {
          this.localTiers = newVal ? [...newVal] : [];
        }
      },
      immediate: true,
      deep: true,
    },
  },
  methods: {
    addTier() {
      const lastTier = this.localTiers[this.localTiers.length - 1];
      const newMinAmount = lastTier ? lastTier.min_amount + 1000 : 1000;
      const newDiscountPercent = lastTier ? Math.min(lastTier.discount_percent + 5, 100) : 5;

      this.localTiers.push({
        min_amount: newMinAmount,
        discount_percent: newDiscountPercent,
      });

      this.emitUpdate();
    },

    removeTier(index) {
      this.localTiers.splice(index, 1);
      this.emitUpdate();
    },

    emitUpdate() {
      this.$emit('update:modelValue', [...this.localTiers]);
    },

    getNextTierAmount(index) {
      const sorted = this.sortedTiers;
      if (index < sorted.length - 1) {
        return sorted[index + 1].min_amount;
      }
      return null;
    },

    formatCurrency(value) {
      return new Intl.NumberFormat('ru-RU').format(value);
    },
  },
};
</script>

<style scoped>
.progressive-tiers-editor {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  background: #f9fafb;
}

.tiers-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.header-label {
  font-weight: 500;
  color: #374151;
}

.btn-add {
  padding: 6px 12px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  transition: background 0.2s;
}

.btn-add:hover {
  background: #2563eb;
}

.empty-state {
  text-align: center;
  padding: 24px;
  color: #6b7280;
}

.empty-state p {
  margin: 0;
}

.tiers-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tier-row {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  padding: 12px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.tier-field {
  flex: 1;
}

.tier-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.input-with-suffix {
  display: flex;
  align-items: center;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  overflow: hidden;
  background: white;
}

.input-with-suffix input {
  flex: 1;
  padding: 8px 10px;
  border: none;
  font-size: 14px;
  min-width: 60px;
}

.input-with-suffix input:focus {
  outline: none;
}

.input-with-suffix .suffix {
  padding: 8px 10px;
  background: #f3f4f6;
  color: #6b7280;
  font-size: 13px;
  white-space: nowrap;
}

.btn-remove {
  width: 32px;
  height: 32px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fee2e2;
  color: #dc2626;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 20px;
  line-height: 1;
  transition: background 0.2s;
}

.btn-remove:hover {
  background: #fecaca;
}

.preview {
  margin-top: 16px;
  padding: 12px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.preview-title {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 8px;
}

.preview-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.preview-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #f3f4f6;
  border-radius: 6px;
  font-size: 13px;
}

.preview-range {
  color: #374151;
}

.preview-discount {
  font-weight: 600;
  color: #059669;
  background: #d1fae5;
  padding: 2px 8px;
  border-radius: 4px;
}

.validation-errors {
  margin-top: 12px;
}

.error {
  color: #dc2626;
  font-size: 13px;
  margin: 4px 0;
  padding: 8px 12px;
  background: #fee2e2;
  border-radius: 6px;
}
</style>
