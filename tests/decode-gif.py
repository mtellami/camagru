"""Independent decoder verification; Pillow is test tooling, never an app dependency."""
import sys
from PIL import Image

image = Image.open(sys.argv[1])
assert image.size == (480, 360)
assert image.n_frames == 3
assert image.info["loop"] == 0
centers = []
for i in range(image.n_frames):
    image.seek(i)
    assert image.info["duration"] == 250
    centers.append(image.convert("RGB").getpixel((240, 180)))
assert centers[0][0] > 180 and centers[0][1] < 60
assert centers[1][1] > 140 and centers[1][0] < 60
assert centers[2][2] > 180 and centers[2][0] < 60
print("Independent GIF decoding passed: dimensions, frames, timing, colors, and looping.")
