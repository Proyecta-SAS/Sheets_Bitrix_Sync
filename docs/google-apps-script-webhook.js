/**
 * Google Sheets -> HostGator webhook.
 *
 * Configure these Script Properties in Apps Script:
 * - WEBHOOK_URL: https://tu-dominio.com/api/sheets/event
 * - WEBHOOK_SECRET: same value as WEBHOOK_SECRET in the project's .env
 * - HEADER_ROW: 1
 * - REQUIRED_HEADERS: Nombre
 */

const CONTROL_STATUS_HEADER = 'Estado sincronización';
const CREATED_STATUSES = ['CREADA', 'PROCESANDO'];

function installTriggers() {
  const spreadsheet = SpreadsheetApp.getActive();
  ScriptApp.getProjectTriggers().forEach((trigger) => {
    const handler = trigger.getHandlerFunction();
    if (handler === 'handleEdit' || handler === 'handleFormSubmit') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('handleEdit').forSpreadsheet(spreadsheet).onEdit().create();
  ScriptApp.newTrigger('handleFormSubmit').forSpreadsheet(spreadsheet).onFormSubmit().create();
}

function handleEdit(event) {
  if (!event || !event.range) {
    return;
  }

  notifyRow(event.range.getRow());
}

function handleFormSubmit(event) {
  if (!event || !event.range) {
    return;
  }

  notifyRow(event.range.getRow());
}

function notifyRow(rowNumber) {
  const properties = PropertiesService.getScriptProperties();
  const webhookUrl = properties.getProperty('WEBHOOK_URL');
  const secret = properties.getProperty('WEBHOOK_SECRET');
  const headerRow = Number(properties.getProperty('HEADER_ROW') || '1');
  const requiredHeaders = String(properties.getProperty('REQUIRED_HEADERS') || 'Nombre')
    .split(',')
    .map((header) => header.trim())
    .filter(Boolean);

  if (!webhookUrl || !secret) {
    throw new Error('Configure WEBHOOK_URL and WEBHOOK_SECRET in Script Properties.');
  }
  if (rowNumber <= headerRow) {
    return;
  }
  if (!rowHasRequiredData(rowNumber, headerRow, requiredHeaders)) {
    return;
  }

  UrlFetchApp.fetch(webhookUrl, {
    method: 'post',
    contentType: 'application/json',
    headers: {
      'X-Webhook-Token': secret,
    },
    payload: JSON.stringify({
      row_number: rowNumber,
    }),
    muteHttpExceptions: true,
  });
}

function rowHasRequiredData(rowNumber, headerRow, requiredHeaders) {
  const sheet = SpreadsheetApp.getActiveSheet();
  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(headerRow, 1, 1, lastColumn).getValues()[0];
  const values = sheet.getRange(rowNumber, 1, 1, lastColumn).getValues()[0];
  const indexByHeader = {};

  headers.forEach((header, index) => {
    indexByHeader[String(header).trim()] = index;
  });

  const statusIndex = indexByHeader[CONTROL_STATUS_HEADER];
  if (statusIndex !== undefined) {
    const status = String(values[statusIndex] || '').trim().toUpperCase();
    if (CREATED_STATUSES.includes(status)) {
      return false;
    }
  }

  return requiredHeaders.every((header) => {
    const index = indexByHeader[header];
    return index !== undefined && String(values[index] || '').trim() !== '';
  });
}
