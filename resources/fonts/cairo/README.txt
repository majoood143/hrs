Cairo (Regular 400, Bold 700) — SIL Open Font License 1.1, see OFL.txt. https://fonts.google.com/specimen/Cairo

Static instances cut from the official variable font (google/fonts, ofl/cairo) because mPDF cannot use variable
fonts. One change was made for mPDF: the GDEF "MarkGlyphSets" table and the two GPOS lookups that use it were
removed, because mPDF's Arabic shaping refuses fonts that contain them ("MarkGlyphSets - Not tested yet").
That only affects the placement of a few diacritics; letter shaping and ligatures are untouched.
