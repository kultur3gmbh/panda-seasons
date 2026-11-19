#!/usr/bin/env python3
"""Build only the WordPress.org review variant, without any external upload."""
from pathlib import Path
import subprocess
import sys
subprocess.run([sys.executable, str(Path(__file__).with_name('build-lite-release.py')), '--review-only'], check=True)
