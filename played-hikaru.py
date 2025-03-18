#
#This part of the program will generate the list of all players that have played against Hikaru
#Should work for any username, probably call this function in the main program
#

import requests
import time


def get_all_games(username):

    base_url = f"https://api.chess.com/pub/player/{username}/games/"
    headers = {"User-Agent": "Hikaru Number Calculator - Chess.com account: gfitz01 - email: gavyvfitz@gmail.com"} #Gotta have this in here or it returns 403s
    archives_response = requests.get(base_url + "archives", headers=headers)

    if archives_response.status_code != 200:
        print("Error fetching archives")
        return {}

    archives = archives_response.json().get("archives", [])
    games_dict = {}

    for archive_url in archives:
        time.sleep(1)  # Respect Chess.com's API rate limits
        response = requests.get(archive_url, headers=headers) #headers=headers is necessary to avoid 403s
        
        if response.status_code != 200:
            print(f"Error fetching data for {archive_url}")
            print(response.status_code)
            continue
        
        games_data = response.json().get("games", [])

        for game in games_data:
            white = game["white"]["username"]
            black = game["black"]["username"]
            game_url = game["url"]

            if white.lower() == username.lower():
                opponent = black
            else:
                opponent = white

            games_dict[opponent] = game_url  # Always overwrite, keeping the last game found

    return games_dict

username = "Hikaru"
games_dict = get_all_games(username)

# Print results
for opponent, game_link in games_dict.items():
    print(f"{opponent}: {game_link}")
