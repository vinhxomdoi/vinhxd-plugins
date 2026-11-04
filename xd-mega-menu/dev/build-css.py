"""Sinh assets/widgets/xd-menu.css từ base.css + mobile.tpl.css.

Chạy:  python3 dev/build-css.py assets/widgets/xd-menu.css
Khối mobile được nhân cho 3 breakpoint (1024 / 767 / 480) của control
"Hiện menu mobile từ"; __N__ được thay bằng selector nav tương ứng.
"""
import pathlib
import sys

HERE = pathlib.Path(__file__).parent
OUT = pathlib.Path(sys.argv[1])

MOBILE_NOTE = """
/* ===== Menu mobile (drawer) theo breakpoint của widget =====
   Mỗi breakpoint một khối giống nhau, chỉ khác class .xd-bp-*.
   :not(#xd) tăng độ ưu tiên (thay cho !important) để thắng màu/nền desktop do
   control Elementor sinh ra; màu mobile vẫn chỉnh được qua biến --xd-m-*. */
"""

REDUCED_MOTION = """/* ===== Giảm chuyển động ===== */
@media (prefers-reduced-motion: reduce) {
	.xd-menu-nav *,
	.xd-menu-nav *::before {
		transition-duration: 0s !important;
		transition-delay: 0s !important;
	}
}
"""

base = (HERE / "base.css").read_text(encoding="utf-8")
tpl = (HERE / "mobile.tpl.css").read_text(encoding="utf-8")

parts = [base, MOBILE_NOTE]
for bp in (1024, 767, 480):
    nav = ".xd-menu-nav.xd-bp-%d:not(#xd)" % bp
    parts.append("@media (max-width: %dpx) {\n%s}\n\n" % (bp, tpl.replace("__N__", nav)))
parts.append(REDUCED_MOTION)

OUT.write_text("".join(parts), encoding="utf-8")
print("Đã ghi", OUT)
