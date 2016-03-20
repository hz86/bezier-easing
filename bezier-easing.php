<?php

/**
 * BezierEasing - use bezier curve for transition easing function
 * by Gaëtan Renaudeau 2014 - 2015 – MIT License
 *
 * Credits: is based on Firefox's nsSMILKeySpline.cpp
 * Usage:
 * $spline = new BezierEasing(0.25, 0.1, 0.25, 1.0)
 * $spline->get(x) => returns the easing value | x must be in [0, 1] range
 *
 */

class BezierEasing
{
	// These values are established by empiricism with tests (tradeoff: performance VS precision)
	private $NEWTON_ITERATIONS = 4;
	private $NEWTON_MIN_SLOPE = 0.001;
	private $SUBDIVISION_PRECISION = 0.0000001;
	private $SUBDIVISION_MAX_ITERATIONS = 10;
	
	private $kSplineTableSize;
	private $kSampleStepSize;
	private $mSampleValues;
	
	private $mX1, $mY1, $mX2, $mY2;
	
	private function A ($aA1, $aA2) { return 1.0 - 3.0 * $aA2 + 3.0 * $aA1; }
	private function B ($aA1, $aA2) { return 3.0 * $aA2 - 6.0 * $aA1; }
	private function C ($aA1)       { return 3.0 * $aA1; }
	
	// Returns x(t) given t, x1, and x2, or y(t) given t, y1, and y2.
	private function calcBezier ($aT, $aA1, $aA2) {
		return (($this->A($aA1, $aA2)*$aT + $this->B($aA1, $aA2))*$aT + $this->C($aA1))*$aT;
	}
	
	// Returns dx/dt given t, x1, and x2, or dy/dt given t, y1, and y2.
	private function getSlope ($aT, $aA1, $aA2) {
		return 3.0 * $this->A($aA1, $aA2)*$aT*$aT + 2.0 * $this->B($aA1, $aA2) * $aT + $this->C($aA1);
	}
	
	private function binarySubdivide ($aX, $aA, $aB, $mX1, $mX2) {
		$currentX = $currentT = 0.0; $i = 0;
		do {
			$currentT = $aA + ($aB - $aA) / 2.0;
			$currentX = $this->calcBezier($currentT, $mX1, $mX2) - $aX;
			if ($currentX > 0.0) {
				$aB = $currentT;
			} else {
				$aA = $currentT;
			}
		} while (abs($currentX) > $this->SUBDIVISION_PRECISION && ++$i < $this->SUBDIVISION_MAX_ITERATIONS);
		return $currentT;
	}
	
	private function newtonRaphsonIterate ($aX, $aGuessT, $mX1, $mX2) {
		for ($i = 0; $i < $this->NEWTON_ITERATIONS; ++$i) {
			$currentSlope = $this->getSlope($aGuessT, $mX1, $mX2);
			if ($currentSlope === 0.0) return $aGuessT;
			$currentX = $this->calcBezier($aGuessT, $mX1, $mX2) - $aX;
			$aGuessT -= $currentX / $currentSlope;
		}
		return $aGuessT;
	}
	
	private function _getTForX ($aX) {
		$intervalStart = 0.0;
		$currentSample = 1;
		$lastSample = $this->kSplineTableSize - 1;
		
		for (; $currentSample !== $lastSample && $this->mSampleValues[$currentSample] <= $aX; ++$currentSample) {
			$intervalStart += $this->kSampleStepSize;
		}
		--$currentSample;
		
		// Interpolate to provide an initial guess for t
		$dist = ($aX - $this->mSampleValues[$currentSample]) / ($this->mSampleValues[$currentSample+1] - $this->mSampleValues[$currentSample]);
		$guessForT = $intervalStart + $dist * $this->kSampleStepSize;
	
		$initialSlope = $this->getSlope($guessForT, $this->mX1, $this->mX2);
		if ($initialSlope >= $this->NEWTON_MIN_SLOPE) {
			return $this->newtonRaphsonIterate($aX, $guessForT, $this->mX1, $this->mX2);
		} else if ($initialSlope === 0.0) {
			return $guessForT;
		} else {
			return $this->binarySubdivide($aX, $intervalStart, $intervalStart + $this->kSampleStepSize, $this->mX1, $this->mX2);
		}
	}
	
	public function __construct($mX1, $mY1, $mX2, $mY2)
	{
		$this->kSplineTableSize = 11;
		$this->kSampleStepSize = 1.0 / ($this->kSplineTableSize - 1.0);
		$points = [$mX1, $mY1, $mX2, $mY2];
		
		for($i = 0; $i < 4; $i++) {
			if(is_nan($points[$i]) || !is_finite($points[$i])) {
				throw new Exception("BezierEasing: points should be integers.");
			}
		}
		
		if ($points[0] < 0 || $points[0] > 1 || $points[2] < 0 || $points[2] > 1) {
			throw new Exception("BezierEasing x values must be in [0, 1] range.");
		}
		
		$this->mX1 = (float)$mX1;
		$this->mY1 = (float)$mY1;
		$this->mX2 = (float)$mX2;
		$this->mY2 = (float)$mY2;
		
		if ($this->mX1 !== $this->mY1 || $this->mX2 !== $this->mY2) {
			for ($i = 0; $i < $this->kSplineTableSize; ++$i) {
				$this->mSampleValues[$i] = $this->calcBezier($i * $this->kSampleStepSize, $mX1, $mX2);
			}
		}
	}
	
	public function get ($x) 
	{
		$x = (float)$x;
		if ($this->mX1 === $this->mY1 && $this->mX2 === $this->mY2) return $x; // linear
		// Because JavaScript number are imprecise, we should guarantee the extremes are right.
		if ($x === 0.0) return 0.0;
		if ($x === 1.0) return 1.0;
		
		return $this->calcBezier($this->_getTForX($x), $this->mY1, $this->mY2);
	}
}