/**
 * Klytos — Minimal QR Code Generator
 * Generates QR codes as inline SVG elements (no external dependencies).
 *
 * Based on the QR Code specification (ISO/IEC 18004).
 * Supports byte-mode encoding for otpauth:// URIs.
 *
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti
 */
(function(global) {
    'use strict';

    // Version capacity table: max data codewords for EC level M (byte mode).
    // Versions 1-10 — sufficient for TOTP URIs (~150 bytes).
    var VERSION_DATA_CAPACITY   = [0, 16, 28, 44, 64, 86, 108, 124, 154, 182, 216];
    var VERSION_EC_CODEWORDS    = [0, 10, 16, 26, 18, 24,  16,  18,  22,  22,  26];
    var VERSION_EC_BLOCKS       = [0,  1,  1,  1,  2,  2,   4,   4,   4,   5,   5];
    var VERSION_TOTAL_CODEWORDS = [0, 26, 44, 70, 100, 134, 172, 196, 242, 292, 346];

    // Alignment pattern center positions per version.
    var ALIGNMENT_POSITIONS = [
        [], [], [6,18], [6,22], [6,26], [6,30], [6,34],
        [6,22,38], [6,24,42], [6,26,46], [6,28,50]
    ];

    // Pre-computed format info bits for mask 0-7 with EC level M (includes BCH + XOR mask).
    var FORMAT_INFO = [
        0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0
    ];

    // Pre-computed version info bits for versions 7-10.
    var VERSION_INFO = [
        0, 0, 0, 0, 0, 0, 0, 0x07C94, 0x085BC, 0x09A99, 0x0A4D3
    ];

    // GF(256) log/exp tables for Reed-Solomon.
    var GF_EXP = new Array(256);
    var GF_LOG = new Array(256);
    (function() {
        var x = 1;
        for (var i = 0; i < 255; i++) {
            GF_EXP[i] = x;
            GF_LOG[x] = i;
            x <<= 1;
            if (x >= 256) x ^= 0x11D;
        }
        GF_EXP[255] = GF_EXP[0];
    })();

    function gfMul(a, b) {
        if (a === 0 || b === 0) return 0;
        return GF_EXP[(GF_LOG[a] + GF_LOG[b]) % 255];
    }

    // Reed-Solomon: generate EC codewords for a data block.
    function rsEncode(data, ecCount) {
        var gen = [1];
        for (var i = 0; i < ecCount; i++) {
            var newGen = new Array(gen.length + 1).fill(0);
            for (var j = 0; j < gen.length; j++) {
                newGen[j] ^= gen[j];
                newGen[j + 1] ^= gfMul(gen[j], GF_EXP[i]);
            }
            gen = newGen;
        }

        var padded = new Array(data.length + ecCount).fill(0);
        for (var i = 0; i < data.length; i++) padded[i] = data[i];

        for (var i = 0; i < data.length; i++) {
            var coef = padded[i];
            if (coef !== 0) {
                for (var j = 0; j < gen.length; j++) {
                    padded[i + j] ^= gfMul(gen[j], coef);
                }
            }
        }

        return padded.slice(data.length);
    }

    // Determine minimum QR version for data length (byte mode).
    function getVersion(dataLen) {
        for (var v = 1; v <= 10; v++) {
            if (VERSION_DATA_CAPACITY[v] >= dataLen) return v;
        }
        return 10;
    }

    // Encode text in byte mode, returning data codewords array.
    function encodeData(text, version) {
        var totalDataCodewords = VERSION_DATA_CAPACITY[version];
        var bits = [];

        // Mode indicator: byte mode = 0100
        bits.push(0, 1, 0, 0);

        // Character count (8 bits for V1-9, 16 bits for V10+)
        var countBits = version <= 9 ? 8 : 16;
        var len = text.length;
        for (var i = countBits - 1; i >= 0; i--) {
            bits.push((len >> i) & 1);
        }

        // Data bytes
        for (var i = 0; i < text.length; i++) {
            var byte = text.charCodeAt(i);
            for (var j = 7; j >= 0; j--) {
                bits.push((byte >> j) & 1);
            }
        }

        // Terminator (up to 4 zeros)
        var maxBits = totalDataCodewords * 8;
        var termLen = Math.min(4, maxBits - bits.length);
        for (var i = 0; i < termLen; i++) bits.push(0);

        // Pad to byte boundary
        while (bits.length % 8 !== 0) bits.push(0);

        // Pad codewords (0xEC, 0x11 alternating)
        var padBytes = [0xEC, 0x11];
        var pi = 0;
        while (bits.length < maxBits) {
            var pb = padBytes[pi % 2];
            for (var j = 7; j >= 0; j--) bits.push((pb >> j) & 1);
            pi++;
        }

        // Convert bit array to byte array
        var codewords = [];
        for (var i = 0; i < bits.length; i += 8) {
            var b = 0;
            for (var j = 0; j < 8; j++) b = (b << 1) | (bits[i + j] || 0);
            codewords.push(b);
        }

        return codewords;
    }

    // Build the complete QR matrix for the given text.
    function buildMatrix(text) {
        var version = getVersion(text.length);
        var size = 17 + version * 4;
        var dataCodewords = encodeData(text, version);
        var ecCount = VERSION_EC_CODEWORDS[version];
        var numBlocks = VERSION_EC_BLOCKS[version];

        // Split data into blocks and compute EC for each.
        var blockSize = Math.floor(dataCodewords.length / numBlocks);
        var largeBlocks = dataCodewords.length % numBlocks;
        var blocks = [];
        var ecBlocks = [];
        var offset = 0;

        for (var i = 0; i < numBlocks; i++) {
            var bLen = blockSize + (i >= numBlocks - largeBlocks ? 1 : 0);
            var block = dataCodewords.slice(offset, offset + bLen);
            blocks.push(block);
            ecBlocks.push(rsEncode(block, ecCount));
            offset += bLen;
        }

        // Interleave data codewords across blocks.
        var interleaved = [];
        var maxBlockLen = blocks[blocks.length - 1].length;
        for (var i = 0; i < maxBlockLen; i++) {
            for (var j = 0; j < numBlocks; j++) {
                if (i < blocks[j].length) interleaved.push(blocks[j][i]);
            }
        }
        // Interleave EC codewords.
        for (var i = 0; i < ecCount; i++) {
            for (var j = 0; j < numBlocks; j++) {
                interleaved.push(ecBlocks[j][i]);
            }
        }

        // Create empty matrix and reserved-flag grid.
        var matrix = [];
        var reserved = [];
        for (var r = 0; r < size; r++) {
            matrix[r] = new Array(size).fill(null);
            reserved[r] = new Array(size).fill(false);
        }

        // ── Finder patterns (3 corners) ──
        function placeFinder(row, col) {
            for (var r = -1; r <= 7; r++) {
                for (var c = -1; c <= 7; c++) {
                    var rr = row + r, cc = col + c;
                    if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue;
                    var isBlack = (r >= 0 && r <= 6 && (c === 0 || c === 6)) ||
                                  (c >= 0 && c <= 6 && (r === 0 || r === 6)) ||
                                  (r >= 2 && r <= 4 && c >= 2 && c <= 4);
                    matrix[rr][cc] = isBlack ? 1 : 0;
                    reserved[rr][cc] = true;
                }
            }
        }
        placeFinder(0, 0);
        placeFinder(0, size - 7);
        placeFinder(size - 7, 0);

        // ── Alignment patterns ──
        var alignPos = ALIGNMENT_POSITIONS[version];
        if (alignPos.length > 0) {
            for (var i = 0; i < alignPos.length; i++) {
                for (var j = 0; j < alignPos.length; j++) {
                    var ar = alignPos[i], ac = alignPos[j];
                    if (reserved[ar][ac]) continue;
                    for (var r = -2; r <= 2; r++) {
                        for (var c = -2; c <= 2; c++) {
                            var isBlack = Math.abs(r) === 2 || Math.abs(c) === 2 || (r === 0 && c === 0);
                            matrix[ar + r][ac + c] = isBlack ? 1 : 0;
                            reserved[ar + r][ac + c] = true;
                        }
                    }
                }
            }
        }

        // ── Timing patterns ──
        for (var i = 8; i < size - 8; i++) {
            if (!reserved[6][i]) {
                matrix[6][i] = (i % 2 === 0) ? 1 : 0;
                reserved[6][i] = true;
            }
            if (!reserved[i][6]) {
                matrix[i][6] = (i % 2 === 0) ? 1 : 0;
                reserved[i][6] = true;
            }
        }

        // ── Dark module ──
        matrix[size - 8][8] = 1;
        reserved[size - 8][8] = true;

        // ── Reserve format info areas ──
        // Top-left: row 8 (cols 0-8) and col 8 (rows 0-8)
        for (var i = 0; i <= 8; i++) {
            reserved[8][i] = true;
            reserved[i][8] = true;
        }
        // Top-right: row 8, cols size-8 to size-1
        for (var i = 0; i < 8; i++) {
            reserved[8][size - 8 + i] = true;
        }
        // Bottom-left: col 8, rows size-7 to size-1
        for (var i = 0; i < 7; i++) {
            reserved[size - 7 + i][8] = true;
        }

        // ── Reserve version info areas (V7+) ──
        if (version >= 7) {
            for (var i = 0; i < 6; i++) {
                for (var j = 0; j < 3; j++) {
                    reserved[i][size - 11 + j] = true;
                    reserved[size - 11 + j][i] = true;
                }
            }
        }

        // ── Place data bits (right-to-left, bottom-to-top zigzag) ──
        var bitIndex = 0;
        var totalBits = interleaved.length * 8;
        var col = size - 1;
        var upward = true;

        while (col >= 0) {
            if (col === 6) col--; // skip timing column
            for (var i = 0; i < size; i++) {
                var row = upward ? (size - 1 - i) : i;
                for (var c = 0; c < 2; c++) {
                    var cc = col - c;
                    if (cc < 0) continue;
                    if (reserved[row][cc]) continue;
                    if (bitIndex < totalBits) {
                        var byteIdx = Math.floor(bitIndex / 8);
                        var bitIdx = 7 - (bitIndex % 8);
                        matrix[row][cc] = (interleaved[byteIdx] >> bitIdx) & 1;
                    } else {
                        matrix[row][cc] = 0;
                    }
                    bitIndex++;
                }
            }
            upward = !upward;
            col -= 2;
        }

        // ── Apply best mask (lowest penalty score) ──
        var bestMask = 0;
        var bestPenalty = Infinity;
        var bestMatrix = null;

        for (var mask = 0; mask < 8; mask++) {
            var m = applyMask(matrix, reserved, size, mask);
            placeFormatInfo(m, size, mask);
            if (version >= 7) placeVersionInfo(m, size, version);
            var penalty = calcPenalty(m, size);
            if (penalty < bestPenalty) {
                bestPenalty = penalty;
                bestMask = mask;
                bestMatrix = m;
            }
        }

        return { matrix: bestMatrix, size: size };
    }

    function applyMask(matrix, reserved, size, mask) {
        var m = [];
        for (var r = 0; r < size; r++) {
            m[r] = matrix[r].slice();
            for (var c = 0; c < size; c++) {
                if (reserved[r][c]) continue;
                var invert = false;
                switch (mask) {
                    case 0: invert = (r + c) % 2 === 0; break;
                    case 1: invert = r % 2 === 0; break;
                    case 2: invert = c % 3 === 0; break;
                    case 3: invert = (r + c) % 3 === 0; break;
                    case 4: invert = (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0; break;
                    case 5: invert = (r * c) % 2 + (r * c) % 3 === 0; break;
                    case 6: invert = ((r * c) % 2 + (r * c) % 3) % 2 === 0; break;
                    case 7: invert = ((r + c) % 2 + (r * c) % 3) % 2 === 0; break;
                }
                if (invert) m[r][c] ^= 1;
            }
        }
        return m;
    }

    function placeFormatInfo(matrix, size, mask) {
        var bits = FORMAT_INFO[mask];
        // Top-left: 15 bits around the finder pattern.
        var positions = [
            [8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],
            [7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]
        ];
        for (var i = 0; i < 15; i++) {
            var bit = (bits >> (14 - i)) & 1;
            matrix[positions[i][0]][positions[i][1]] = bit;
        }
        // Bottom-left (col 8, rows size-1 down to size-7).
        for (var i = 0; i < 7; i++) {
            matrix[size - 1 - i][8] = (bits >> i) & 1;
        }
        // Top-right (row 8, cols size-8 to size-1).
        for (var i = 7; i < 15; i++) {
            matrix[8][size - 15 + i] = (bits >> i) & 1;
        }
    }

    function placeVersionInfo(matrix, size, version) {
        if (version < 7) return;
        var bits = VERSION_INFO[version];
        for (var i = 0; i < 18; i++) {
            var bit = (bits >> i) & 1;
            var r = Math.floor(i / 3);
            var c = size - 11 + (i % 3);
            matrix[r][c] = bit;
            matrix[c][r] = bit;
        }
    }

    function calcPenalty(matrix, size) {
        var penalty = 0;

        // Rule 1: runs of 5+ same-color modules.
        for (var r = 0; r < size; r++) {
            var count = 1;
            for (var c = 1; c < size; c++) {
                if (matrix[r][c] === matrix[r][c - 1]) {
                    count++;
                    if (count === 5) penalty += 3;
                    else if (count > 5) penalty += 1;
                } else {
                    count = 1;
                }
            }
        }
        for (var c = 0; c < size; c++) {
            var count = 1;
            for (var r = 1; r < size; r++) {
                if (matrix[r][c] === matrix[r - 1][c]) {
                    count++;
                    if (count === 5) penalty += 3;
                    else if (count > 5) penalty += 1;
                } else {
                    count = 1;
                }
            }
        }

        // Rule 2: 2x2 same-color blocks.
        for (var r = 0; r < size - 1; r++) {
            for (var c = 0; c < size - 1; c++) {
                var v = matrix[r][c];
                if (v === matrix[r][c+1] && v === matrix[r+1][c] && v === matrix[r+1][c+1]) {
                    penalty += 3;
                }
            }
        }

        return penalty;
    }

    // Render QR matrix as inline SVG string.
    function renderSVG(qr, moduleSize, quietZone) {
        moduleSize = moduleSize || 4;
        quietZone = quietZone || 4;
        var size = qr.size;
        var totalSize = (size + quietZone * 2) * moduleSize;

        var rects = [];
        for (var r = 0; r < size; r++) {
            for (var c = 0; c < size; c++) {
                if (qr.matrix[r][c]) {
                    rects.push('<rect x="' + ((c + quietZone) * moduleSize) +
                              '" y="' + ((r + quietZone) * moduleSize) +
                              '" width="' + moduleSize +
                              '" height="' + moduleSize + '"/>');
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + totalSize + ' ' + totalSize +
               '" width="' + totalSize + '" height="' + totalSize + '" shape-rendering="crispEdges">' +
               '<rect width="100%" height="100%" fill="#ffffff" rx="8"/>' +
               '<g fill="#000000">' + rects.join('') + '</g></svg>';
    }

    /**
     * Generate a QR code and insert it into a target element.
     *
     * @param {string} targetId  - ID of the container element.
     * @param {string} text      - The text/URL to encode.
     * @param {object} [options] - Optional: { moduleSize: 4, quietZone: 4 }
     */
    function generate(targetId, text, options) {
        options = options || {};
        var el = document.getElementById(targetId);
        if (!el) return;

        var qr = buildMatrix(text);
        var svg = renderSVG(qr, options.moduleSize || 4, options.quietZone || 4);
        el.innerHTML = svg;
    }

    global.KlytosQR = { generate: generate };

})(window);
