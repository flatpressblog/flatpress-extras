# Blosxom importer
Imports entries from the legacy blog system [Blosxom](http://blosxom.sourceforge.net/) into FlatPress.

See the idea and discussion [on the FlatPress support forum](https://forum.flatpress.org/viewtopic.php?f=2&t=101).

## How it works
The process is broadly this:

- download your Blosxom files, including all the sub-directories for categories, but make sure to maintain the date/time filestamp of individual files - this is used to timestamp the entry for Flatpress. WinSCP does this (Filezilla doesnt)
- make sure the categories only ONE DIRECTORY DEEP. Move any sub-sub-directories up to the top level
- rename all the directories to numbers. These are used to tag the entries and can then be recreated within FlatPress
- copy the script.py and template files to the directory the folders are stored in
- edit the template file to have the header/footer you want. The content, date and categories will be changed for the entries
- run the script
- a new fp-content directory will be created with all your entries
- copy this to your flatpress site and rebuild the index

The script does the following
- rename s the file to entry<date>-<time>.txt based upon the date modified date
- copies the file to a new subfolder in FlatPress /content folder based upon year and month
- cuts the first line from the file (and deletes the first line break)
- prefixes the file with:
<code>VERSION|fp-1.1|SUBJECT|<first line from file>|CONTENT|</code>
- suffixes with:
<code>|AUTHOR|miksmith|DATE|<1566926569>|CATEGORIES|<orig_dir_name>|</code>

BIG shout out to James O'Connor for putting the script together after we'd worked out what to do!