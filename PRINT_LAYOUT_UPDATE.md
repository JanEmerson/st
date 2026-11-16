Batch accountability print layout update

Updated frontend/pages/assignment_batch_print.php to match the existing assignment print form styling: company logo and title, bordered section heading, clean metadata, six-column asset/employee signature table, and two administrative signoffs.

Asset codes remain available in an on-screen reference section but are excluded from print. The Part / Model field uses the saved batch snapshot part_number (it is not replaced with live asset data, preserving historical records). No database changes.

Install: back up your existing file, then replace frontend/pages/assignment_batch_print.php. Test Print / Save PDF using A4 landscape.
