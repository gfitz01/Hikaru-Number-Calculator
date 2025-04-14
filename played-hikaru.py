#
#This part of the program will generate the list of all players that have played against Hikaru
#Should work for any username, probably call this function in the main program
#

import requests
import time
import csv
headers = {"User-Agent": "Hikaru Number Calculator - Chess.com account: gfitz01 - email: gavyvfitz@gmail.com"} #Gotta have this in here or it returns 403s
TITLE_PRECEDENCE = [
    "GM", "WGM", "IM", "WIM", "FM", "WFM", "CM", "WCM", "NM"
]


def get_all_games(username):

    base_url = f"https://api.chess.com/pub/player/{username}/games/"
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

def get_titled_players_by_category():
    """
    Fetch titled players from Chess.com and categorize them by title.

    :return: Dictionary {title: set(players)}
    """
    titled_players = {title: set() for title in TITLE_PRECEDENCE}

    for title in TITLE_PRECEDENCE:
        url = f"https://api.chess.com/pub/titled/{title}"
        response = requests.get(url, headers=headers)
        
        if response.status_code == 200:
            titled_players[title] = set(response.json().get("players", []))
        else:
            print(f"Error fetching {title} players")

        time.sleep(1)  # Respect API rate limits
    
    return titled_players

def filter_highest_precedence_titled_players(games_dict):
    """
    Filters the highest precedence titled players who are in the games dictionary.

    :param games_dict: Dictionary {opponent: game_link}
    :return: Dictionary {highest_precedence_titled_player: game_link}
    """
    titled_players_by_category = get_titled_players_by_category()

    # Find the highest precedence title with at least one opponent in games_dict
    for title in TITLE_PRECEDENCE:
        titled_players = titled_players_by_category[title]
        matching_players = {player: games_dict[player] for player in games_dict if player in titled_players}

        if matching_players:
            return matching_players  # Return only the highest-ranked category

    return {}  # No titled opponents found

def read_csv_to_dict(filename):
    """
    Reads a CSV file and converts it into a dictionary.
    Assumes the first column is the key and the second column is the value.

    :param filename: Path to the CSV file
    :return: Dictionary with keys and values from the CSV
    """
    data_dict = {}
    with open(filename, mode='r', newline='', encoding='utf-8') as csvfile:
        reader = csv.DictReader(csvfile)
        for row in reader:
            # Assuming "Username" is the key and "Game URL" is the value
            data_dict[row["Username"]] = row["Game URL"]
    return data_dict

def find_highest_rated_player(usernames):
    """
    Finds the highest-rated player from a list of usernames using Chess.com's API.

    :param usernames: List of usernames to check
    :return: Tuple (username, rating) of the highest-rated player
    """
    highest_rated_player = None
    highest_rating = -1

    for username in usernames:
        url = f"https://api.chess.com/pub/player/{username}/stats"
        response = requests.get(url, headers=headers)

        if response.status_code == 200:
            stats = response.json()
            # Assuming we are checking the "chess_blitz" rating
            rating = stats.get("chess_blitz", {}).get("last", {}).get("rating", 0)
            if rating > highest_rating:
                highest_rating = rating
                highest_rated_player = username
        else:
            print(f"Error fetching stats for {username}")

        time.sleep(1)  # Respect API rate limits

    return highest_rated_player

hikaru_dict = read_csv_to_dict("played-hikaru.csv")

username = "gfitz01"  
nextUsername = ""
outDict = {}
while True:
    if username in hikaru_dict:
        outDict[username] = hikaru_dict[username]
        print(f"Found {username} in the CSV. Game URL: {hikaru_dict[username]}")
        break
    games_dict = get_all_games(username)
    if not games_dict:
        print("No games found for this username.")
        break
    title_dict = filter_highest_precedence_titled_players(games_dict)
    if title_dict:
        nextUsername = find_highest_rated_player(title_dict.keys())
    else:
        nextUsername = find_highest_rated_player(games_dict.keys())
    outDict[username] = games_dict[nextUsername]
    username = nextUsername
    print (f"Next username: {nextUsername}")

print(outDict)