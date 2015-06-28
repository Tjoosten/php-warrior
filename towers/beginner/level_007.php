<?php
//  ------
// |>a S @|
//  ------

$this->description(__('You feel a wall right in front of you and an opening behind you.'));
$this->tip(__('You are not as effective at attacking backward. Use $warrior->feel()->is_wall() and $warrior->pivot() to turn around.'));

$this->time_bonus(30);
$this->ace_score(50);
$this->size(6, 1);
$this->stairs(0, 0);

$this->warrior(5, 0, 'east')->add_abilities(['pivot']);

$this->unit('archer', 1, 0, 'east');
$this->unit('thick_sludge', 3, 0, 'east');
