#!/usr/bin/env python3
"""Compile the project's simple, non-plural PO catalogs without gettext."""
import ast
import struct
import sys


def compile_po(source, target):
    messages = {}
    msgid = msgstr = None
    active = None
    for raw in open(source, encoding="utf-8"):
        line = raw.strip()
        if line.startswith("msgid "):
            if msgid is not None:
                messages[msgid] = msgstr or ""
            msgid, msgstr, active = ast.literal_eval(line[6:]), "", "id"
        elif line.startswith("msgstr "):
            msgstr, active = ast.literal_eval(line[7:]), "str"
        elif line.startswith('"'):
            value = ast.literal_eval(line)
            if active == "id":
                msgid += value
            elif active == "str":
                msgstr += value
        elif not line and msgid is not None:
            messages[msgid] = msgstr or ""
            msgid = msgstr = active = None
    if msgid is not None:
        messages[msgid] = msgstr or ""

    keys = sorted(messages)
    ids = b"".join(key.encode() + b"\0" for key in keys)
    values = b"".join(messages[key].encode() + b"\0" for key in keys)
    count = len(keys)
    key_offset = 28
    value_offset = key_offset + count * 8
    ids_offset = value_offset + count * 8
    values_offset = ids_offset + len(ids)
    key_table, value_table = [], []
    cursor = 0
    for key in keys:
        encoded = key.encode()
        key_table.append((len(encoded), ids_offset + cursor))
        cursor += len(encoded) + 1
    cursor = 0
    for key in keys:
        encoded = messages[key].encode()
        value_table.append((len(encoded), values_offset + cursor))
        cursor += len(encoded) + 1
    with open(target, "wb") as output:
        output.write(struct.pack("<7I", 0x950412DE, 0, count, key_offset, value_offset, 0, 0))
        for entry in key_table + value_table:
            output.write(struct.pack("<2I", *entry))
        output.write(ids)
        output.write(values)


if __name__ == "__main__":
    compile_po(sys.argv[1], sys.argv[2])
