export function useDealValidation() {
  function normalizeMissingPayload(payload = {}) {
    const grouped = payload.grouped_missing || {}

    return {
      valid: payload.valid === true,
      missingFields: payload.missing_fields || [],
      missingFieldsGrouped: payload.missing_fields_grouped || {
        sections: grouped.sections || [],
      },
      missingFieldsGroupedByStage: payload.missing_fields_grouped_by_stage || {
        stages: grouped.by_stage || [],
      },
      groupedMissing: {
        sections: grouped.sections || payload.missing_fields_grouped?.sections || [],
        by_stage: grouped.by_stage || payload.missing_fields_grouped_by_stage?.stages || [],
      },
      message: payload.message || 'Missing required fields',
    }
  }

  return {
    normalizeMissingPayload,
  }
}

