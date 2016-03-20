# bezier-easing

```
$easing = new BezierEasing(0, 0, 1, 0.5);
// easing allows to project x in [0.0,1.0] range onto the bezier-curve defined by the 4 points (see schema below).

echo($easing->get(0.0))."\r\n"; // 0.0
echo($easing->get(0.5))."\r\n"; // 0.3125
echo($easing->get(1.0))."\r\n"; // 1.0
```
