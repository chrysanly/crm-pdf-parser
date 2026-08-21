"""Glyph repair tests: what `_sanitise` must do before parsing ever runs.

The PDFs that produced these cases are real uploads. Every one of them looked
like a parser bug and was in fact an unmapped glyph.
"""

from app.extraction import _sanitise


def test_a_leading_unmapped_glyph_becomes_a_bullet():
    text = "(cid:127) Provide efficient cashiering and customer service."

    assert _sanitise(text) == "• Provide efficient cashiering and customer service."


def test_unmapped_glyphs_inside_a_line_are_dropped_not_guessed_at():
    assert _sanitise("Cash Handling (cid:3) POS Operation") == "Cash Handling POS Operation"


def test_a_symbol_font_marker_opening_a_line_becomes_a_bullet():
    # ZapfDingbats draws the envelope icon beside the address; pdfminer reports
    # its text as the letter "n".
    text = "n salaheldinziada.6@gmail.com"

    assert _sanitise(text, frozenset({"n"})) == "• salaheldinziada.6@gmail.com"


def test_a_real_word_starting_with_a_marker_letter_is_left_alone():
    text = "no formal training in this area"

    assert _sanitise(text, frozenset({"n"})) == text


def test_a_spaced_undecodable_character_was_a_separator():
    # Without this the date range never matches and the job loses its dates.
    assert _sanitise("June 2023 � Present") == "June 2023 – Present"


def test_an_undecodable_character_between_letters_was_an_apostrophe():
    assert _sanitise("Dunkin� Donuts") == "Dunkin’ Donuts"


def test_runs_of_spaces_collapse_but_lines_are_preserved():
    assert _sanitise("Engineer      Acme\nDubai") == "Engineer Acme\nDubai"
