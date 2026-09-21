/**
 * Furniture Sales CRM -> Google Sheets mirror.
 *
 * Paste this whole file into the spreadsheet's Apps Script editor
 * (Extensions > Apps Script), set SHARED_SECRET below to the same value as
 * GOOGLE_SHEETS_SECRET in the CRM's .env, then Deploy > New deployment >
 * Web app, with "Execute as: Me" and "Who has access: Anyone".
 *
 * The deployment URL is public, so every request carries an HMAC-SHA256
 * signature over "<timestamp>.<payload>" and anything older than five minutes
 * is refused. Apps Script does not expose request headers to doPost, so the
 * signature travels inside the body and the payload arrives as a string, which
 * is what both sides sign.
 *
 * Rows are matched on column A (the CRM row id), so re-sending an order
 * updates its existing line instead of adding a duplicate.
 */

var SHARED_SECRET = 'PASTE_THE_SAME_SECRET_AS_GOOGLE_SHEETS_SECRET_HERE';

var MAX_CLOCK_SKEW_SECONDS = 300;

function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return respond_({ ok: false, error: 'Empty request body' });
    }

    var envelope = JSON.parse(e.postData.contents);
    verifySignature_(envelope);

    var payload = JSON.parse(envelope.payload);

    if (payload.action === 'ping') {
      return respond_({
        ok: true,
        spreadsheet: SpreadsheetApp.getActiveSpreadsheet().getName()
      });
    }

    if (payload.action === 'sync') {
      return respond_(syncSheets_(payload.sheets || {}));
    }

    return respond_({ ok: false, error: 'Unknown action: ' + payload.action });
  } catch (err) {
    return respond_({ ok: false, error: String((err && err.message) || err) });
  }
}

/** A plain GET is only ever a human checking the URL is alive. */
function doGet() {
  return respond_({ ok: true, service: 'CRM sheet sync' });
}

/* -------------------------------------------------------------------------
 | Security
 | ----------------------------------------------------------------------- */

function verifySignature_(envelope) {
  var secret = activeSecret_();

  if (!secret || secret.indexOf('PASTE_THE_SAME_SECRET') === 0) {
    throw new Error('SHARED_SECRET is not set in the Apps Script');
  }

  if (!envelope || !envelope.ts || !envelope.sig || typeof envelope.payload !== 'string') {
    throw new Error('Malformed request');
  }

  var age = Math.abs(Math.floor(Date.now() / 1000) - parseInt(envelope.ts, 10));
  if (isNaN(age) || age > MAX_CLOCK_SKEW_SECONDS) {
    throw new Error('Request timestamp expired');
  }

  // The charset is explicit on purpose: the two-argument overload picks one
  // itself and disagrees with PHP on any byte above 0x7F. The CRM also sends
  // the payload as pure ASCII (\uXXXX escapes), so the two agree twice over.
  var expected = toHex_(
    Utilities.computeHmacSha256Signature(
      envelope.ts + '.' + envelope.payload,
      secret,
      Utilities.Charset.UTF_8
    )
  );

  if (!constantTimeEquals_(expected, String(envelope.sig))) {
    throw new Error('Bad signature');
  }
}

/** Script Properties win, so the secret need not sit in the source. */
function activeSecret_() {
  var stored = PropertiesService.getScriptProperties().getProperty('SHARED_SECRET');

  return stored || SHARED_SECRET;
}

function toHex_(bytes) {
  var hex = '';
  for (var i = 0; i < bytes.length; i++) {
    var b = (bytes[i] < 0 ? bytes[i] + 256 : bytes[i]).toString(16);
    hex += b.length === 1 ? '0' + b : b;
  }
  return hex;
}

function constantTimeEquals_(a, b) {
  if (a.length !== b.length) {
    return false;
  }
  var diff = 0;
  for (var i = 0; i < a.length; i++) {
    diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return diff === 0;
}

/* -------------------------------------------------------------------------
 | Sync
 | ----------------------------------------------------------------------- */

function syncSheets_(sheets) {
  // One writer at a time: two cron ticks overlapping would otherwise both
  // read the same id map and append the same order twice.
  var lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    var result = { ok: true, written: {} };

    for (var name in sheets) {
      if (Object.prototype.hasOwnProperty.call(sheets, name)) {
        result.written[name] = syncTab_(name, sheets[name]);
      }
    }

    return result;
  } finally {
    lock.releaseLock();
  }
}

function syncTab_(name, spec) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(name) || ss.insertSheet(name);
  var headers = spec.headers || [];
  var width = headers.length;

  writeHeaders_(sheet, headers);

  var removed = deleteRows_(sheet, spec.deletes || []);

  // Read the id column only after the deletions, so the row numbers are current.
  var rowById = buildIdMap_(sheet);
  var updated = 0;
  var appended = [];

  var rows = spec.rows || [];
  for (var i = 0; i < rows.length; i++) {
    var row = padRow_(rows[i], width);
    var id = String(row[0]);

    if (rowById[id]) {
      sheet.getRange(rowById[id], 1, 1, width).setValues([row]);
      updated++;
    } else {
      appended.push(row);
    }
  }

  if (appended.length) {
    sheet.getRange(sheet.getLastRow() + 1, 1, appended.length, width).setValues(appended);
  }

  return { updated: updated, appended: appended.length, deleted: removed };
}

function writeHeaders_(sheet, headers) {
  if (!headers.length) {
    return;
  }

  var current = sheet.getLastColumn()
    ? sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0]
    : [];

  if (current.join('') === headers.join('')) {
    return;
  }

  sheet.getRange(1, 1, 1, headers.length)
    .setValues([headers])
    .setFontWeight('bold');

  sheet.setFrozenRows(1);
}

function buildIdMap_(sheet) {
  var lastRow = sheet.getLastRow();
  var map = {};

  if (lastRow < 2) {
    return map;
  }

  var ids = sheet.getRange(2, 1, lastRow - 1, 1).getValues();

  for (var i = 0; i < ids.length; i++) {
    var id = String(ids[i][0]).trim();
    if (id) {
      // First occurrence wins, so a hand-pasted duplicate never steals the row.
      if (!map[id]) {
        map[id] = i + 2;
      }
    }
  }

  return map;
}

function deleteRows_(sheet, ids) {
  if (!ids || !ids.length) {
    return 0;
  }

  var rowById = buildIdMap_(sheet);
  var targets = [];

  for (var i = 0; i < ids.length; i++) {
    var row = rowById[String(ids[i])];
    if (row) {
      targets.push(row);
    }
  }

  // Bottom-up, so deleting one row does not shift the next target.
  targets.sort(function (a, b) { return b - a; });

  for (var j = 0; j < targets.length; j++) {
    sheet.deleteRow(targets[j]);
  }

  return targets.length;
}

/** Nulls become blanks, and every row is padded to the header width. */
function padRow_(row, width) {
  var out = [];

  for (var i = 0; i < width; i++) {
    var value = i < row.length ? row[i] : '';
    out.push(value === null || value === undefined ? '' : value);
  }

  return out;
}

function respond_(body) {
  return ContentService
    .createTextOutput(JSON.stringify(body))
    .setMimeType(ContentService.MimeType.JSON);
}
