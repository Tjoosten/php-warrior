<?php

namespace PHPWarrior;

class Game {

  public $profile;

  public function start() {
    UI::puts(__('Welcome to PHP Warrior'));

    if (file_exists(Config::$path_prefix . '/.pwprofile')) {
      $this->profile = Profile::load(Config::$path_prefix . '/.pwprofile');
    } elseif (!is_dir(Config::$path_prefix . '/phpwarrior')) {
      $this->make_game_directory();
    }
    if (is_null($this->profile)) {
      $this->profile = $this->choose_profile();
    }
    if ($this->profile->is_epic()) {
      if ($this->profile->is_level_after_epic()) {
        $this->go_back_to_normal_mode();
      } else {
        $this->play_epic_mode();
      }
    } else {
      $this->play_normal_mode();
    }
  }

  public function make_game_directory() {
    if (UI::ask(__('No phpwarrior directory found, would you like to create one?'))) {
      mkdir(Config::$path_prefix . '/phpwarrior');
    } else {
      UI::puts(__('Unable to continue without directory.'));
      exit;
    }
  }


  public function play_epic_mode() {
    if (Config::$delay) {
      Config::$delay /= 2;
    } # speed up UI since we're going to be doing a lot here
    $this->profile->current_epic_score = 0;
    $this->profile->current_epic_grades = [];
    if (Config::$practice_level) {
      $this->current_level = $this->next_level = null;
      $this->profile->level_number = Config::$practice_level;
      $this->play_current_level();
    } else {
      $playing = true;
      $this->profile->level_number = 0;
      while ($playing) {
        $this->current_level = $this->next_level = null;
        $this->profile->level_number += 1;
        $playing = $this->play_current_level();
      }
      $this->profile->save(); # saves the score for epic mode
    }
  }

  public function play_normal_mode() {
    if ($this->profile->current_level()->number == 0) {
      $this->prepare_next_level();
      UI::puts(sprintf(
        __("First level has been generated. See the phpwarrior/%s/README for instructions."),
        $this->profile->directory_name()
        ));
    } else {
      $this->play_current_level();
    }
  }

  public function play_current_level() {
    $continue = true;
    $this->current_level()->load_player();
    UI::puts(sprintf(
      __("Starting Level %s"),
      $this->current_level()->number
    ));
    $this->current_level()->play();
    if ($this->current_level()->is_passed()) {
      if ($this->next_level()->is_exists()) {
        UI::puts(__('Success! You have found the stairs.'));
      } else {
        UI::puts(__('CONGRATULATIONS! You have climbed to the top of the tower and rescued the fair maiden PHP.'));
        $continue = false;
      }
      $this->current_level()->tally_points();
      if ($this->profile->is_epic()) {
        if (!$continue) {
          UI::puts($this->final_report());
        }
      } else {
        $this->request_next_level();
      }
    } else {
      $continue = false;
      UI::puts(sprintf(
        __("Sorry, you failed level %s. Change your script and try again."),
        $this->current_level()->number
      ));
      if (!Config::$skip_input && $this->current_level()->clue && UI::ask(__("Would you like to read the additional clues for this level?"))) {
        UI::puts($this->current_level()->clue);
      }
    }
    return $continue;
  }

  public function request_next_level() {
    if (!Config::$skip_input && ($this->next_level()->is_exists() ? UI::ask(__("Would you like to continue on to the next level?")) : UI::ask(__("Would you like to continue on to epic mode?")))) {
      if ($this->next_level()->is_exists()) {
        $this->prepare_next_level();
        UI::puts(sprintf(
          __("See the updated README in the phpwarrior/%s directory."),
            $this->profile->directory_name()
        ));
      } else {
        $this->prepare_epic_mode();
        UI::puts(__("Run rubywarrior again to play epic mode."));
      }
    } else {
      UI::puts(__("Staying on current level. Try to earn more points next time."));
    }
  }

  public function prepare_next_level() {
    $this->profile->next_level()->generate_player_files();
    $this->profile->level_number += 1;
    $this->profile->save(); // this saves score and new abilities too
  }

  public function prepare_epic_mode() {
    $this->profile->enable_epic_mode();
    $this->profile->level_number = 0;
    $this->profile->save(); # this saves score too
  }

  public function go_back_to_normal_mode() {
    $this->profile->enable_normal_mode();
    $this->prepare_next_level();
    UI::puts(__("Another level has been added since you started epic, going back to normal mode."));
    UI::puts(__("See the updated README in the rubywarrior/#{profile.directory_name} directory."));
  }

  public function profiles() {
    $map = [];
    foreach ($this->profile_paths() as $path) {
      $map[] = Profile::load($path);
    }
    return $map;
  }

  public function profile_paths() {
    return glob(Config::$path_prefix . '/phpwarrior/**/.pwprofile');
  }

  public function new_profile() {
    $profile = new Profile();
    $profile->tower_path = UI::choose('tower', $this->towers())->path;
    $profile->warrior_name = UI::request(__('Enter a name for your warrior: '));
    return $profile;
  }

  public function towers() {
    $map = [];
    foreach ($this->tower_paths() as $path) {
      $map[] = new Tower($path);
    }
    return $map;
  }

  public function tower_paths() {
    return glob(__DIR__ .'/../../towers/*');
  }

  public function current_level() {
    if (!isset($this->current_level)) {
      $this->current_level = $this->profile->current_level();
    }

    return $this->current_level;
  }

  public function next_level() {
    if (!isset($this->next_level)) {
      $this->next_level = $this->profile->next_level();
    }

    return $this->next_level;
  }

  public function final_report() {
    if ($this->profile->calculate_average_grade() && !Config::$practice_level) {
      $report = "";
      $letter = Level::grade_letter($this->profile->calculate_average_grade());
      $report .= sprintf(
        __("Your average grade for this tower is: %s"),
        $letter
      )."\n\n";
      foreach ($this->profile->current_epic_grades as $key => $level) {
        $letter = Level::grade_letter($level);
        $report .= "  Level {$key}: {$letter}\n";
      }
      $report .= "\n". __("To practice a level, use the -l option:\n\n  php-warrior -l 3");
      return $report;
    }
  }

  public function choose_profile() {
    $profile = UI::choose('profile', array_merge($this->profiles(),[[':new',__('New Profile')]]));
    if (is_array($profile) && $profile[0] == ':new') {
      $profile = $this->new_profile();
    }
    /*
    if profile == :new
      profile = new_profile
      if profiles.any? { |p| p.player_path == profile.player_path }
        if UI.ask("Are you sure you want to replace your existing profile for this tower?")
          UI.puts "Replacing existing profile."
          profile
        else
          UI.puts "Not replacing profile."
          exit
        end
      else
        profile
      end
    else
      profile
    end
    */
    return $profile;
  }
}
