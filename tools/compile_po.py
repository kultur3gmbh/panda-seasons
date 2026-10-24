#!/usr/bin/env python3
"""Compile the repository's simple singular gettext PO files to MO files."""

import ast
import struct
import sys
from pathlib import Path


def messages(path):
    catalog = {}
    msgid = []
    msgstr = []
    section = None
    fuzzy = False

    def commit():
        nonlocal msgid, msgstr, fuzzy
        if msgid and not fuzzy:
            catalog["".join(msgid)] = "".join(msgstr)
        msgid, msgstr, fuzzy = [], [], False

    for raw_line in path.read_text(encoding="utf-8").splitlines() + [""]:
        line = raw_line.strip()
        if line.startswith("#,") and "fuzzy" in line:
            fuzzy = True
        elif line.startswith("msgid "):
            if msgid:
                commit()
            section = "id"
            msgid.append(ast.literal_eval(line[5:].strip()))
        elif line.startswith("msgstr "):
            section = "str"
            msgstr.append(ast.literal_eval(line[6:].strip()))
        elif line.startswith('"'):
            value = ast.literal_eval(line)
            (msgid if section == "id" else msgstr).append(value)
        elif not line and msgid:
            commit()
            section = None
    return catalog


def compile_mo(source, target):
    catalog = messages(source)
    ids = sorted(catalog)
    encoded_ids = [item.encode("utf-8") for item in ids]
    encoded_strings = [catalog[item].encode("utf-8") for item in ids]
    count = len(ids)
    key_offset = 7 * 4 + count * 16
    value_offset = key_offset + sum(len(item) + 1 for item in encoded_ids)
    key_table = []
    value_table = []
    cursor = key_offset
    for item in encoded_ids:
        key_table.append((len(item), cursor))
        cursor += len(item) + 1
    cursor = value_offset
    for item in encoded_strings:
        value_table.append((len(item), cursor))
        cursor += len(item) + 1
    output = [struct.pack("<7I", 0x950412DE, 0, count, 28, 28 + count * 8, 0, 0)]
    output.extend(struct.pack("<2I", *entry) for entry in key_table)
    output.extend(struct.pack("<2I", *entry) for entry in value_table)
    output.append(b"\0".join(encoded_ids) + b"\0")
    output.append(b"\0".join(encoded_strings) + b"\0")
    target.write_bytes(b"".join(output))


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("usage: compile_po.py SOURCE.po TARGET.mo")
    compile_mo(Path(sys.argv[1]), Path(sys.argv[2]))
