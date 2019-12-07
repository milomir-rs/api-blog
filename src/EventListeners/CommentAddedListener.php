<?php


namespace App\EventListeners;


use App\Entity\Comment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class CommentAddedListener
{
    const NO_REPLY_EMAIL = 'noreply@example.com';

    /**
     * @var \Swift_Mailer
     */
    private $mailer;
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var Environment
     */
    private $twig;

    /**
     * @param \Swift_Mailer $mailer
     * @param EntityManagerInterface $em
     * @param Environment $twig
     */
    public function __construct(\Swift_Mailer $mailer, EntityManagerInterface $em, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->em = $em;
        $this->twig = $twig;
    }

    /**
     * @param Comment $comment
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function postPersist(Comment $comment)
    {
        $post = $comment->getPost();
        $usernames = $this->getUsernamesFromText($comment->getText());

        if (empty($usernames) || !in_array($post->getCreator()->getUsername(), $usernames)) {
            $usernames[] = $post->getCreator()->getUsername();
        }

        $subject = 'New comment on post "' . $post->getTitle() . '".';
        $body = $this->twig->render(
            'emails/commentAdded.html.twig',
            ['postTitle' => $post->getTitle()]
        );

        foreach ($usernames as $username) {
            $user = $this->findUserByUsername($username);
            if ($user) {
                $this->sendMessage(self::NO_REPLY_EMAIL, $user->getEmail(), $subject, $body);
            }
        }
    }

    /**
     * @param string $text
     * @return array
     */
    private function getUsernamesFromText(string $text)
    {
        preg_match_all('(\[[@]([a-zA-Z0-9])+\])', $text, $mentionTags);
        $usernames = [];
        foreach ($mentionTags[0] as $mentionTag) {
            $usernames[] = substr($mentionTag, 2, -1);
        }
        return $usernames;
    }

    /**
     * @param string $username
     * @return User|null
     */
    private function findUserByUsername($username)
    {
        return $this->getUserRepository()->findOneBy(['username' => $username]);
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $subject
     * @param string $body
     */
    private function sendMessage($from, $to, $subject, $body)
    {
        $message = (new \Swift_Message($subject))
            ->setFrom($from)
            ->setTo($to)
            ->setBody($body);

        $this->mailer->send($message);
    }

    /**
     * @return \App\Repository\UserRepository
     */
    private function getUserRepository()
    {
        return $this->em->getRepository(User::class);
    }

}