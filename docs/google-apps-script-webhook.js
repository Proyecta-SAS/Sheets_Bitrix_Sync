/**
 * Google Sheets -> HostGator webhook.
 *
 * Configure these Script Properties in Apps Script:
 * - WEBHOOK_URL: https://sheetsxbitrix.proyectasolutions.co/api/sheets/event
 * - WEBHOOK_SECRET: same value as WEBHOOK_SECRET in the project's .env
 * - HEADER_ROW: 1
 * - REQUIRED_HEADERS: Nombre
 * - SHEET_NAME: optional; use it when the spreadsheet has more than one tab
 *
 * Run installTriggers() once after configuring the properties.
 * Run testLastRow() to force a one-row manual test.
 */

const CONTROL_STATUS_HEADER = 'Estado sincronización';
const FINISHED_STATUSES = ['CREADA', 'PROCESANDO'];
const LAST_PROCESSED_ROW_PROPERTY = 'LAST_PROCESSED_ROW';

function installTriggers() {
  const spreadsheet = SpreadsheetApp.getActive();
  const config = getConfig();
  const sheet = getConfiguredSheet(spreadsheet, config);

  ScriptApp.getProjectTriggers().forEach((trigger) => {
    const handler = trigger.getHandlerFunction();
    if (['handleEdit', 'handleFormSubmit', 'handleChange'].includes(handler)) {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  PropertiesService.getScriptProperties().setProperty(
    LAST_PROCESSED_ROW_PROPERTY,
    String(Math.max(config.headerRow, sheet.getLastRow()))
  );

  ScriptApp.newTrigger('handleEdit').forSpreadsheet(spreadsheet).onEdit().create();
  ScriptApp.newTrigger('handleFormSubmit').forSpreadsheet(spreadsheet).onFormSubmit().create();
  ScriptApp.newTrigger('handleChange').forSpreadsheet(spreadsheet).onChange().create();
}

function handleEdit(event) {
  if (!event || !event.range) {
    return;
  }

  const config = getConfig();
  const sheet = event.range.getSheet();
  if (!isConfiguredSheet(sheet, config)) {
    return;
  }

  const startRow = event.range.getRow();
  const endRow = startRow + event.range.getNumRows() - 1;
  notifyRows(sheet, startRow, endRow, config);
  rememberLastProcessedRow(sheet, config);
}

function handleFormSubmit(event) {
  if (!event || !event.range) {
    scanNewRows();
    return;
  }

  const config = getConfig();
  const sheet = event.range.getSheet();
  if (!isConfiguredSheet(sheet, config)) {
    return;
  }

  notifyRows(sheet, event.range.getRow(), event.range.getRow(), config);
  rememberLastProcessedRow(sheet, config);
}

function handleChange() {
  scanNewRows();
}

function testLastRow() {
  const spreadsheet = SpreadsheetApp.getActive();
  const config = getConfig();
  const sheet = getConfiguredSheet(spreadsheet, config);
  const rowNumber = sheet.getLastRow();

  if (rowNumber <= config.headerRow) {
    throw new Error('No hay filas de datos para probar.');
  }
  if (!rowHasRequiredData(sheet, rowNumber, config)) {
    throw new Error(`La fila ${rowNumber} no tiene los encabezados requeridos completos: ${config.requiredHeaders.join(', ')}`);
  }

  notifyRow(rowNumber, config);
}

function scanNewRows() {
  const spreadsheet = SpreadsheetApp.getActive();
  const config = getConfig();
  const sheet = getConfiguredSheet(spreadsheet, config);
  const properties = PropertiesService.getScriptProperties();
  const lastKnownRow = Number(properties.getProperty(LAST_PROCESSED_ROW_PROPERTY) || String(config.headerRow));
  const lastRow = sheet.getLastRow();

  if (lastRow <= Math.max(config.headerRow, lastKnownRow)) {
    return;
  }

  notifyRows(sheet, lastKnownRow + 1, lastRow, config);
  properties.setProperty(LAST_PROCESSED_ROW_PROPERTY, String(lastRow));
}

function notifyRows(sheet, startRow, endRow, config) {
  const firstDataRow = config.headerRow + 1;
  const from = Math.max(startRow, firstDataRow);
  const to = Math.max(endRow, from);

  for (let rowNumber = from; rowNumber <= to; rowNumber += 1) {
    if (rowHasRequiredData(sheet, rowNumber, config)) {
      notifyRow(rowNumber, config);
    }
  }
}

function notifyRow(rowNumber, config) {
  const response = UrlFetchApp.fetch(config.webhookUrl, {
    method: 'post',
    contentType: 'application/json',
    headers: {
      'X-Webhook-Token': config.secret,
    },
    payload: JSON.stringify({
      row_number: rowNumber,
    }),
    muteHttpExceptions: true,
  });

  const status = response.getResponseCode();
  if (status < 200 || status >= 300) {
    throw new Error(`Webhook failed for row ${rowNumber}. HTTP ${status}: ${response.getContentText()}`);
  }
}

function rowHasRequiredData(sheet, rowNumber, config) {
  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(config.headerRow, 1, 1, lastColumn).getValues()[0];
  const values = sheet.getRange(rowNumber, 1, 1, lastColumn).getValues()[0];
  const indexByHeader = {};

  headers.forEach((header, index) => {
    indexByHeader[String(header).trim()] = index;
  });

  const statusIndex = indexByHeader[CONTROL_STATUS_HEADER];
  if (statusIndex !== undefined) {
    const status = String(values[statusIndex] || '').trim().toUpperCase();
    if (FINISHED_STATUSES.includes(status)) {
      return false;
    }
  }

  return config.requiredHeaders.every((header) => {
    const index = indexByHeader[header];
    return index !== undefined && String(values[index] || '').trim() !== '';
  });
}

function rememberLastProcessedRow(sheet, config) {
  const properties = PropertiesService.getScriptProperties();
  const lastKnownRow = Number(properties.getProperty(LAST_PROCESSED_ROW_PROPERTY) || String(config.headerRow));
  properties.setProperty(LAST_PROCESSED_ROW_PROPERTY, String(Math.max(lastKnownRow, sheet.getLastRow())));
}

function getConfig() {
  const properties = PropertiesService.getScriptProperties();
  const webhookUrl = properties.getProperty('WEBHOOK_URL');
  const secret = properties.getProperty('WEBHOOK_SECRET');

  if (!webhookUrl || !secret) {
    throw new Error('Configure WEBHOOK_URL and WEBHOOK_SECRET in Script Properties.');
  }

  return {
    webhookUrl,
    secret,
    headerRow: Number(properties.getProperty('HEADER_ROW') || '1'),
    requiredHeaders: String(properties.getProperty('REQUIRED_HEADERS') || 'Nombre')
      .split(',')
      .map((header) => header.trim())
      .filter(Boolean),
    sheetName: String(properties.getProperty('SHEET_NAME') || '').trim(),
  };
}

function getConfiguredSheet(spreadsheet, config) {
  if (config.sheetName !== '') {
    const sheet = spreadsheet.getSheetByName(config.sheetName);
    if (!sheet) {
      throw new Error(`No existe la pestaña configurada: ${config.sheetName}`);
    }

    return sheet;
  }

  return spreadsheet.getActiveSheet();
}

function isConfiguredSheet(sheet, config) {
  return config.sheetName === '' || sheet.getName() === config.sheetName;
}
