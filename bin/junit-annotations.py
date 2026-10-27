#!/usr/bin/env python3
"""Print GitHub Actions ::error annotations for failed/errored JUnit test cases."""
import sys
import xml.etree.ElementTree as ET


def esc(text):
    return text.replace('%', '%25').replace('\r', '').replace('\n', '%0A')


def main(path):
    try:
        root = ET.parse(path).getroot()
    except (OSError, ET.ParseError) as exc:
        print(f'::warning::No JUnit report ({exc})')
        return
    for case in root.iter('testcase'):
        for kind in ('failure', 'error'):
            node = case.find(kind)
            if node is None:
                continue
            name = f"{case.get('class', '')}::{case.get('name', '')}"
            body = (node.text or node.get('message') or '')[:1500]
            file = case.get('file', '')
            line = case.get('line', '1')
            print(f'::error file={file},line={line},title={esc(name)}::{esc(body)}')


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else 'junit.xml')
